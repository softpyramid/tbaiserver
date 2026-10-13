<?php

namespace App\Providers;

use App\Models\AlertRule;
use App\Models\CaseNotification;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\IncidenceEstimate;
use App\Models\IncidenceTarget;
use App\Models\PopulationDenominator;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use App\Observers\CommandCacheObserver;
use App\Support\CommandApi\CommandData;
use App\Support\CommandCache;
use App\Support\CommandReturnQuery;
use App\Support\DataScope;
use App\Support\Diagnosis\DiagnosisEngine;
use App\Support\Diagnosis\SimulatedDiagnosisEngine;
use App\Support\Impersonation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(DataScope::class);
        $this->app->scoped(CommandData::class);
        $this->app->scoped(CommandReturnQuery::class);
        $this->registerDiagnosisEngine();
    }

    /**
     * The diagnosis engine named by tbai.diagnosis.engine. Add a real engine
     * here under its own key; an unknown key fails loudly instead of
     * quietly falling back to the simulated one.
     */
    protected function registerDiagnosisEngine(): void
    {
        $this->app->bind(DiagnosisEngine::class, function ($app): DiagnosisEngine {
            $engine = (string) config('tbai.diagnosis.engine', 'simulated');

            return match ($engine) {
                'simulated' => $app->make(SimulatedDiagnosisEngine::class),
                default => throw new InvalidArgumentException("Unknown diagnosis engine [{$engine}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureGates();
        $this->configureImpersonation();
        $this->configureCommandCache();
        $this->app->terminating(fn () => $this->app->forgetScopedInstances());
    }

    /**
     * Drop cached command figures when the underlying rows change.
     */
    protected function configureCommandCache(): void
    {
        $observer = CommandCacheObserver::class;

        DailyReturn::observe($observer);
        CaseNotification::observe($observer);
        PopulationDenominator::observe($observer);
        IncidenceEstimate::observe($observer);
        IncidenceTarget::observe($observer);
        Facility::observe($observer);
        City::observe($observer);
        AlertRule::observe($observer);
        ProgrammeAssignment::observe($observer);

        DB::listen(function ($query): void {
            static $busting = false;

            if ($busting) {
                return;
            }

            $sql = strtolower($query->sql);

            if (! preg_match('/\b(insert|update|delete|replace)\b/', $sql)) {
                return;
            }

            $watched = [
                'daily_returns',
                'case_notifications',
                'population_denominators',
                'incidence_estimates',
                'incidence_targets',
                'facilities',
                'cities',
                'alert_rules',
                'programme_assignments',
                'sector_indicators',
                'district_indicators',
                'treatment_outcomes',
                'mdr_rr_cases',
                'ai_test_counts',
                'contact_tracing',
                'hiv_testing',
            ];

            foreach ($watched as $table) {
                if (! str_contains($sql, $table)) {
                    continue;
                }

                $busting = true;
                CommandCache::bump();
                $busting = false;

                return;
            }
        });
    }

    /**
     * Programme gates shared by the command center and administration screens.
     */
    protected function configureGates(): void
    {
        Gate::define('administer', fn (User $user): bool => $user->isTbaiAdmin());

        Gate::define('viewCommandCenter', fn (User $user): bool => $user->programmeAssignments->isNotEmpty());
    }

    /**
     * While an admin or in-charge is viewing as someone, nothing is sent to
     * that person, and signing out records the end of the impersonation.
     */
    protected function configureImpersonation(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event): ?bool {
            $target = $this->impersonatedUser();

            if ($target === null) {
                return null;
            }

            $notifiable = $event->notifiable;

            if ($notifiable instanceof User && $notifiable->is($target)) {
                return false;
            }

            if ($notifiable instanceof AnonymousNotifiable) {
                $routes = array_map(
                    fn ($route): string => is_string($route) ? strtolower($route) : '',
                    [$notifiable->routeNotificationFor('mail')],
                );

                if (in_array(strtolower($target->email), $routes, true)) {
                    return false;
                }
            }

            return null;
        });

        Event::listen(MessageSending::class, function (MessageSending $event): ?bool {
            $target = $this->impersonatedUser();

            if ($target === null) {
                return null;
            }

            foreach ($event->message->getTo() as $address) {
                if (strtolower($address->getAddress()) === strtolower($target->email)) {
                    return false;
                }
            }

            return null;
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(Impersonation::class)->recordSignOut(request(), $event->user);
            }
        });
    }

    private function impersonatedUser(): ?User
    {
        if (! app(Impersonation::class)->active()) {
            return null;
        }

        $user = request()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Limit public access requests by IP address.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('command-api', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('access-requests', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip() ?? 'unknown');
        });
    }
}

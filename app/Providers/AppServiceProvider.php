<?php

namespace App\Providers;

use App\Models\User;
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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }

    /**
     * Programme gates shared by the command center and administration screens.
     */
    protected function configureGates(): void
    {
        Gate::define('administer', fn (User $user): bool => $user->isTbaiAdmin());

        Gate::define('viewCommandCenter', fn (User $user): bool => $user->programmeAssignments()->exists());
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

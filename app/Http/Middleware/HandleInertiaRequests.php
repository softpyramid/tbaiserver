<?php

namespace App\Http\Middleware;

use App\Data\UserTeam;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\DataScope;
use App\Support\FacilityTabs;
use App\Support\Impersonation;
use App\Support\NavigationShell;
use App\Support\RoleHome;
use App\Support\SidebarNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $facilityTeam = $this->facilityTeam($request, $user);
        $isTbaiAdmin = $user?->isTbaiAdmin() ?? false;
        $canOpenProfile = $user !== null
            && $facilityTeam?->facility !== null
            && Gate::forUser($user)->allows('openProfile', $facilityTeam->facility);

        $showValidatedClaims = (bool) config('tbai.show_validated_claims');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'homeUrl' => $user instanceof User ? app(RoleHome::class)->url($user) : route('home'),
            'pendingInvitations' => fn () => $this->pendingInvitations($user),
            'auth' => [
                'user' => $user,
            ],
            'tbai' => [
                'showValidatedClaims' => $showValidatedClaims,
                'showSupporterLogos' => (bool) config('tbai.show_supporter_logos'),
                'showPartnerLine' => (bool) config('tbai.show_partner_line'),
                'showTelehealth' => (bool) config('tbai.show_telehealth'),
                'developmentNotice' => $showValidatedClaims
                    ? null
                    : config('tbai.development_notice'),
                'partnerLine' => config('tbai.show_partner_line')
                    ? config('tbai.partner_line')
                    : null,
                'contact' => config('tbai.contact'),
            ],
            'registerUrl' => function () use ($user): ?string {
                $team = $user?->currentTeam;

                if ($team === null || ! $team->facility()->exists()) {
                    return null;
                }

                return route('returns.today', ['current_team' => $team->slug]);
            },
            'profileUrl' => $canOpenProfile
                ? route('facilities.edit', ['current_team' => $facilityTeam->slug])
                : null,
            'myFacilityUrl' => $user !== null && $facilityTeam !== null && ($isTbaiAdmin || $user->belongsToTeam($facilityTeam))
                ? route('facilities.home', ['current_team' => $facilityTeam->slug])
                : null,
            'impersonation' => $user instanceof User && app(Impersonation::class)->active($request)
                ? [
                    'name' => $user->name,
                    'stopUrl' => route('impersonation.stop'),
                    'banner' => __('entry.impersonation_banner', ['name' => $user->name]),
                    'returnLabel' => __('entry.impersonation_return'),
                    'stopLabel' => __('entry.impersonation_stop'),
                ]
                : null,
            'nav' => $this->navigation($request, $user, $facilityTeam),
            'i18n' => Inertia::once(fn (): array => [
                'locale' => app()->getLocale(),
                'dir' => in_array(app()->getLocale(), ['ur', 'ar', 'fa'], true) ? 'rtl' : 'ltr',
                'entry' => trans('entry'),
            ]),
            'screeningUrl' => config('tbai.features.screening') && $user !== null && $facilityTeam !== null && $user->belongsToTeam($facilityTeam)
                ? route('screening.create', ['current_team' => $facilityTeam->slug])
                : null,
            'facilitiesUrl' => $isTbaiAdmin ? route('facilities.index') : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $this->displayTeam($user, $facilityTeam),
            'teams' => fn () => $this->facilityChoices($user),
            'scopeLabel' => fn () => $this->scopeLabel($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function navigation(Request $request, ?User $user, ?Team $facilityTeam): array
    {
        $phone = app(FacilityTabs::class)->for($user, $facilityTeam, $request);

        return [
            'shell' => app(NavigationShell::class)->for($user, $facilityTeam),
            'activeTab' => $phone['active'],
            'tabs' => $phone['tabs'],
            'more' => $phone['more'],
            'commandTabs' => app(SidebarNavigation::class)->commandAreaTabs($user),
            'tabsLabel' => __('entry.nav_tabs'),
            'moreLabel' => __('entry.nav_more'),
            'logoutLabel' => __('entry.nav_log_out'),
            'groups' => app(SidebarNavigation::class)->groups($user, $facilityTeam),
        ];
    }

    /**
     * @return list<array{code: string, inviterName: string, team: array{name: string, slug: string}}>
     */
    private function pendingInvitations(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $email = strtolower($user->email);

        return TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation): array => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ])
            ->all();
    }

    /**
     * Facility behind the current URL, or the user's current team when that team is a facility.
     */
    private function facilityTeam(Request $request, ?User $user): ?Team
    {
        $routed = $request->route('current_team');

        if ($routed instanceof Team) {
            $routed->loadMissing('facility');

            if ($routed->facility !== null) {
                return $routed;
            }
        }

        $current = $user?->currentTeam;

        if ($current === null) {
            return null;
        }

        $current->loadMissing('facility');

        return $current->facility === null ? null : $current;
    }

    /**
     * Facility shown in the sidebar. Personal teams stay hidden.
     */
    private function displayTeam(?User $user, ?Team $facilityTeam): ?UserTeam
    {
        $team = $this->chosenFacility($user, $facilityTeam);

        if ($user === null || $team === null) {
            return null;
        }

        return $user->toUserTeam($team);
    }

    /**
     * @return list<UserTeam>
     */
    private function facilityChoices(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->teams()
            ->whereHas('facility')
            ->orderByRaw('LOWER(teams.name)')
            ->get()
            ->map(fn (Team $team): UserTeam => $user->toUserTeam($team))
            ->all();
    }

    /**
     * Read-only scope for a city officer, provincial viewer, or country viewer.
     */
    private function scopeLabel(?User $user): ?string
    {
        if ($user === null || $user->isTbaiAdmin()) {
            return null;
        }

        if (! $user->programmeAssignments()->exists()) {
            return null;
        }

        $label = app(DataScope::class)->label($user);

        if ($label === '' || $label === __('entry.scope_all')) {
            return null;
        }

        return $label;
    }

    private function chosenFacility(?User $user, ?Team $facilityTeam): ?Team
    {
        if ($user === null) {
            return null;
        }

        if ($facilityTeam !== null && $user->belongsToTeam($facilityTeam)) {
            return $facilityTeam;
        }

        $current = $user->currentTeam;
        $current?->loadMissing('facility');

        if ($current?->facility !== null && $user->belongsToTeam($current)) {
            return $current;
        }

        $team = $user->teams()->whereHas('facility')->first();

        return $team instanceof Team ? $team : null;
    }
}

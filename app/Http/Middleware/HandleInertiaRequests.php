<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\NavigationShell;
use App\Support\RoleHome;
use App\Support\SidebarNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'nav' => [
                'shell' => app(NavigationShell::class)->for($user, $facilityTeam),
                'groups' => app(SidebarNavigation::class)->groups($user, $facilityTeam),
            ],
            'i18n' => [
                'locale' => app()->getLocale(),
                'dir' => in_array(app()->getLocale(), ['ur', 'ar', 'fa'], true) ? 'rtl' : 'ltr',
                'entry' => trans('entry'),
            ],
            'screeningUrl' => $user !== null && $facilityTeam !== null && $user->belongsToTeam($facilityTeam)
                ? route('screening.create', ['current_team' => $facilityTeam->slug])
                : null,
            'facilitiesUrl' => $isTbaiAdmin ? route('facilities.index') : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
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
}

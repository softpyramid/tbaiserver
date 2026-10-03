<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use App\Support\NavigationShell;
use App\Support\SidebarNavigation;
use Illuminate\Http\Request;
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
            && $facilityTeam !== null
            && ($isTbaiAdmin || $user->belongsToTeam($facilityTeam));

        $showValidatedClaims = (bool) config('tbai.show_validated_claims');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
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

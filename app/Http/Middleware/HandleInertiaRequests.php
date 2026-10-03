<?php

namespace App\Http\Middleware;

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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}

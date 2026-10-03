<?php

namespace App\Http\Controllers;

use App\Enums\Sector;
use App\Http\Requests\StoreIncidenceTargetRequest;
use App\Http\Requests\UpdateAlertRuleRequest;
use App\Models\AlertRule;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\IncidenceTarget;
use App\Support\AdminAttention;
use App\Support\CommandCenterPage;
use App\Support\DailyReturnDetail;
use App\Support\DailyReturnListing;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommandCenterController extends Controller
{
    /**
     * Cumulative Data for the viewer's programme scope.
     */
    public function index(Request $request, CommandCenterPage $page): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('command/index', [
            ...$page->cumulative($request, $user),
            'attention' => app(AdminAttention::class)->for($user),
        ]);
    }

    public function map(Request $request, CommandCenterPage $page): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('command/map', $page->map($request, $user));
    }

    public function alerts(Request $request, CommandCenterPage $page): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('command/alerts', $page->alerts($request, $user));
    }

    public function updateAlertRule(UpdateAlertRuleRequest $request, AlertRule $alertRule): RedirectResponse
    {
        $alertRule->update($request->validated());

        return back();
    }

    public function drivers(Request $request, CommandCenterPage $page): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('command/drivers', $page->drivers($request, $user));
    }

    public function incidence(Request $request, CommandCenterPage $page): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('command/incidence', $page->incidence($request, $user));
    }

    public function storeIncidence(StoreIncidenceTargetRequest $request): RedirectResponse
    {
        $data = $request->validated();

        IncidenceTarget::query()->updateOrCreate(
            [
                'province_id' => (int) $data['province_id'],
                'year' => (int) $data['year'],
            ],
            [
                'month' => null,
                'value' => $data['value'],
            ],
        );

        return back();
    }

    public function sector(Request $request, string $sector, CommandCenterPage $page): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        $sectorEnum = Sector::tryFrom($sector);
        abort_unless($sectorEnum !== null, 404);

        return Inertia::render('command/sector', $page->sector($request, $user, $sectorEnum));
    }

    public function district(Request $request, string $sector, City $city, CommandCenterPage $page, DataScope $scope): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        $sectorEnum = Sector::tryFrom($sector);
        abort_unless($sectorEnum !== null, 404);
        abort_unless($scope->allowsCity($user, $city->id), 403);

        return Inertia::render('command/district', $page->district($request, $user, $sectorEnum, $city));
    }

    public function returns(Request $request, DailyReturnListing $listing): Response|StreamedResponse
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return $listing->respond($request, $user, administration: false);
    }

    public function showReturn(Request $request, DailyReturn $dailyReturn, DataScope $scope, DailyReturnDetail $detail): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null && $scope->allowsFacility($user, $dailyReturn->facility_id), 403);

        return $detail->render($dailyReturn);
    }
}

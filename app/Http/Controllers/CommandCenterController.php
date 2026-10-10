<?php

namespace App\Http\Controllers;

use App\Enums\AreaType;
use App\Enums\Sector;
use App\Http\Requests\StoreIncidenceEstimateRequest;
use App\Http\Requests\StoreIncidenceTargetRequest;
use App\Http\Requests\UpdateAlertRuleRequest;
use App\Models\AlertRule;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\IncidenceEstimate;
use App\Models\IncidenceTarget;
use App\Support\AdminAttention;
use App\Support\AuditLogger;
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

    /**
     * The full-screen command center (prototype layout). Every figure on it
     * comes from the /api/command endpoints; this only boots the page.
     */
    public function center(): Response
    {
        Gate::authorize('viewCommandCenter');

        return Inertia::render('command/center', [
            'backUrl' => route('command.index'),
            'backLabel' => __('entry.back_to_app'),
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
                'source' => $data['source'] ?? null,
            ],
        );

        return back();
    }

    public function storeIncidenceEstimate(StoreIncidenceEstimateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $areaType = AreaType::from($data['area_type']);
        $areaId = $areaType === AreaType::Country ? null : (int) $data['area_id'];

        $estimate = IncidenceEstimate::query()
            ->when($areaType === AreaType::Country, fn ($q) => $q->whereNull('area_id'), fn ($q) => $q->where('area_id', $areaId))
            ->where('area_type', $areaType->value)
            ->first();

        if ($estimate === null) {
            $estimate = new IncidenceEstimate;
            $estimate->area_type = $areaType->value;
            $estimate->area_id = $areaId;
        }

        $estimate->year = (int) $data['year'];
        $estimate->rate_per_100k = $data['rate_per_100k'];
        $estimate->lower_bound = $data['lower_bound'] ?? null;
        $estimate->upper_bound = $data['upper_bound'] ?? null;
        $estimate->estimated_cases = $data['estimated_cases'] ?? null;
        $estimate->source = $data['source'];
        $estimate->method = $data['method'];
        $estimate->reference_url = $data['reference_url'] ?? null;
        $estimate->entered_by = $request->user()?->id;
        $estimate->save();

        $audit->write($request->user(), 'incidence_estimates.saved', $estimate, [
            'area_type' => $areaType->value,
            'area_id' => $areaId,
            'year' => $estimate->year,
            'rate_per_100k' => $estimate->rate_per_100k,
            'source' => $estimate->source,
            'method' => $estimate->method,
        ]);

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

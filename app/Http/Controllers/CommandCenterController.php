<?php

namespace App\Http\Controllers;

use App\Models\DailyReturn;
use App\Models\Facility;
use App\Support\DailyReturnDetail;
use App\Support\DailyReturnListing;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommandCenterController extends Controller
{
    /**
     * Scoped landing for programme roles. Not an analytics dashboard.
     */
    public function index(Request $request, DataScope $scope): Response
    {
        Gate::authorize('viewCommandCenter');

        $user = $request->user();
        abort_unless($user !== null, 403);

        $facilities = Facility::query();
        $scope->constrainFacilities($facilities, $user);

        $returns = DailyReturn::query()->whereIn(
            'facility_id',
            (clone $facilities)->select('facilities.id'),
        );

        return Inertia::render('command/index', [
            'title' => __('entry.nav_command'),
            'scope' => $scope->label($user),
            'intro' => __('entry.command_intro'),
            'facilitiesLine' => __('entry.command_facility_count', [
                'count' => (clone $facilities)->count(),
            ]),
            'returnsLine' => __('entry.command_return_count', [
                'count' => (clone $returns)->count(),
            ]),
            'facilitiesUrl' => $user->isTbaiAdmin()
                ? route('facilities.index')
                : route('command.facilities'),
            'facilitiesLabel' => __('entry.nav_facilities'),
            'returnsUrl' => $user->isTbaiAdmin()
                ? route('admin.daily-returns.index')
                : route('command.returns'),
            'returnsLabel' => __('entry.nav_daily_returns'),
        ]);
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

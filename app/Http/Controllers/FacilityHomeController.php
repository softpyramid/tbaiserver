<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Support\EntryDate;
use App\Support\TeamArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FacilityHomeController extends Controller
{
    /**
     * Overview tab of the Facility area: where the facility stands today and
     * where to go next. Everyone on the staff can open it.
     */
    public function __invoke(Request $request, Team $current_team, TeamArea $area): Response
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        Gate::authorize('view', $facility);

        $facility->loadMissing('city');
        $user = $request->user();
        abort_unless($user !== null, 403);

        $today = EntryDate::now()->toDateString();
        $submittedToday = $facility->dailyReturns()->whereDate('reported_on', $today)->exists();
        $onFile = $facility->dailyReturns()->count();

        $tiles = [
            [
                'key' => 'today',
                'label' => __('entry.nav_register_long'),
                'figure' => $submittedToday ? __('entry.overview_submitted') : __('entry.overview_not_submitted'),
                'caption' => EntryDate::label(),
            ],
            [
                'key' => 'on_file',
                'label' => __('entry.returns_on_file'),
                'figure' => (string) $onFile,
                'caption' => __('entry.overview_on_file_caption'),
            ],
        ];

        if (Gate::allows('viewRoster', $current_team)) {
            $tiles = [...$tiles, ...array_slice($area->cardsFor($current_team), 0, 2)];
        }

        $links = [];

        if (Gate::allows('viewReturns', $current_team)) {
            $links[] = [
                'href' => route('returns.history', $current_team),
                'label' => __('entry.nav_returns_history'),
            ];
        }

        if (Gate::allows('openProfile', $facility)) {
            $links[] = [
                'href' => route('facilities.edit', $current_team),
                'label' => __('entry.open_profile'),
            ];
        }

        return Inertia::render('facilities/home', [
            'area' => $area->header($user, $current_team, 'overview'),
            'tiles' => $tiles,
            'action' => [
                'href' => route('returns.today', $current_team),
                'label' => __('entry.overview_open_register'),
            ],
            'dateLabel' => EntryDate::label(),
            'lines' => [
                ['label' => __('entry.sector'), 'value' => $facility->sector->label()],
                ['label' => __('entry.kind'), 'value' => $facility->kind->label()],
                ['label' => __('entry.city'), 'value' => $facility->city->name],
                ['label' => __('entry.column_status'), 'value' => $facility->status->value === 'active' ? __('entry.active') : __('entry.inactive')],
            ],
            'links' => $links,
        ]);
    }
}

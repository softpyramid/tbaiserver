<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Support\EntryDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FacilityHomeController extends Controller
{
    /**
     * Read-only summary of one facility for its staff.
     */
    public function __invoke(Request $request, Team $current_team): Response
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        Gate::authorize('view', $facility);

        $facility->loadMissing('city');
        $user = $request->user();
        abort_unless($user !== null, 403);

        $links = [
            [
                'href' => route('returns.today', $current_team),
                'label' => __('entry.nav_register_long'),
            ],
        ];

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
            'title' => __('entry.nav_facility'),
            'dateLabel' => EntryDate::label(),
            'name' => $current_team->name,
            'lines' => [
                ['label' => __('entry.sector'), 'value' => $facility->sector->label()],
                ['label' => __('entry.kind'), 'value' => $facility->kind->label()],
                ['label' => __('entry.city'), 'value' => $facility->city->name],
                ['label' => __('entry.active'), 'value' => $facility->status->value === 'active' ? __('entry.active') : __('entry.inactive')],
                ['label' => __('entry.returns_on_file'), 'value' => (string) $facility->dailyReturns()->count()],
            ],
            'links' => $links,
        ]);
    }
}

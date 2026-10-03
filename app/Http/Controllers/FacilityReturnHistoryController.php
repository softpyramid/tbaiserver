<?php

namespace App\Http\Controllers;

use App\Models\DailyReturn;
use App\Models\Team;
use App\Support\EntryDate;
use App\Support\ResourceResponse;
use App\Support\ReturnRevisionPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacilityReturnHistoryController extends Controller
{
    /**
     * One row per date for this facility. Does not create returns.
     */
    public function __invoke(Request $request, Team $current_team, ReturnRevisionPresenter $revisions): Response|StreamedResponse
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        Gate::authorize('viewReturns', $current_team);

        $query = DailyReturn::query()
            ->where('facility_id', $facility->id)
            ->with(['revisions.editor', 'submitter'])
            ->orderByDesc('reported_on');

        $columns = [
            ['key' => 'date', 'label' => __('entry.column_date')],
            ['key' => 'suspects', 'label' => __('entry.column_suspects')],
            ['key' => 'tested', 'label' => __('entry.column_tested')],
            ['key' => 'positive', 'label' => __('entry.column_positive')],
            ['key' => 'positivity', 'label' => __('entry.column_positivity')],
            ['key' => 'edited_by', 'label' => __('entry.column_edited_by')],
        ];

        $cells = function (DailyReturn $return) use ($revisions): array {
            $latest = $return->revisions->sortByDesc('id')->first();
            $editor = $latest?->editor;

            return [
                'date' => EntryDate::day($return->reported_on),
                'suspects' => (string) $return->suspects,
                'tested' => (string) $return->tested,
                'positive' => (string) $return->positive,
                'positivity' => $revisions->positivity($return->positive, $return->tested),
                'edited_by' => $editor === null ? $return->submitter->name : $editor->name,
            ];
        };

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'returns-history.csv',
                array_column($columns, 'label'),
                $query->get()->map(fn (DailyReturn $return): array => array_values($cells($return))),
            );
        }

        $page = $query->paginate(15)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_returns_history'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (DailyReturn $return): array => [
                'id' => (string) $return->id,
                'href' => route('returns.today', [
                    'current_team' => $current_team->slug,
                    'reported_on' => EntryDate::day($return->reported_on),
                ]),
                'cells' => $cells($return),
                'controls' => [],
            ])->values()->all(),
            filters: [],
            sort: 'date',
            direction: 'desc',
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
        );
    }
}

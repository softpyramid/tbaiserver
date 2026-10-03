<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\ScreeningSession;
use App\Support\ResourceResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreeningSessionAdminController extends Controller
{
    /**
     * Facility, date, score, and suspect flag only. No patient identifiers.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort(
            $request,
            ['date', 'facility', 'score'],
            'date',
            'desc',
        );

        $query = ScreeningSession::query()
            ->select(['id', 'facility_id', 'occurred_on', 'score', 'is_suspect'])
            ->with('facility.team');

        $facilityId = $request->integer('facility');

        if ($facilityId > 0) {
            $query->where('facility_id', $facilityId);
        }

        $suspect = $request->string('suspect')->toString();

        if ($suspect === '1' || $suspect === '0') {
            $query->where('is_suspect', $suspect === '1');
        }

        $from = ResourceResponse::date($request, 'from');
        $to = ResourceResponse::date($request, 'to');

        if ($from !== null) {
            $query->whereDate('occurred_on', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('occurred_on', '<=', $to);
        }

        if ($sort === 'facility') {
            $query->orderBy(
                Facility::query()
                    ->select('teams.name')
                    ->join('teams', 'teams.id', '=', 'facilities.team_id')
                    ->whereColumn('facilities.id', 'screening_sessions.facility_id')
                    ->limit(1),
                $direction,
            );
        } elseif ($sort === 'score') {
            $query->orderBy('score', $direction);
        } else {
            $query->orderBy('occurred_on', $direction);
        }

        $columns = [
            ['key' => 'facility', 'label' => __('entry.column_facility')],
            ['key' => 'date', 'label' => __('entry.column_date')],
            ['key' => 'score', 'label' => __('entry.column_score')],
            ['key' => 'suspect', 'label' => __('entry.column_suspect')],
        ];

        $cells = fn (ScreeningSession $session): array => [
            'facility' => $session->facility->team->name,
            'date' => $session->occurred_on->toDateString(),
            'score' => (string) $session->score,
            'suspect' => $session->is_suspect ? __('entry.yes') : __('entry.no'),
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'screening-sessions.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (ScreeningSession $session): array => array_values($cells($session))),
            );
        }

        $page = $query->paginate(15)->withQueryString();
        $facilities = Facility::query()->with('team')->get()->sortBy(
            fn (Facility $facility): string => mb_strtolower($facility->team->name),
        );

        return ResourceResponse::page(
            title: __('entry.nav_screening_sessions'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (ScreeningSession $session): array => [
                'id' => (string) $session->id,
                'href' => null,
                'cells' => $cells($session),
                'controls' => [],
            ])->values()->all(),
            filters: [
                [
                    'key' => 'facility',
                    'label' => __('entry.column_facility'),
                    'type' => 'select',
                    'value' => $facilityId > 0 ? (string) $facilityId : '',
                    'options' => array_merge(
                        [['value' => '', 'label' => __('entry.any')]],
                        $facilities->map(fn (Facility $facility): array => [
                            'value' => (string) $facility->id,
                            'label' => $facility->team->name,
                        ])->values()->all(),
                    ),
                ],
                [
                    'key' => 'suspect',
                    'label' => __('entry.column_suspect'),
                    'type' => 'select',
                    'value' => $suspect === '1' || $suspect === '0' ? $suspect : '',
                    'options' => [
                        ['value' => '', 'label' => __('entry.any')],
                        ['value' => '1', 'label' => __('entry.yes')],
                        ['value' => '0', 'label' => __('entry.no')],
                    ],
                ],
                [
                    'key' => 'from',
                    'label' => __('entry.from_date'),
                    'type' => 'date',
                    'value' => $from ?? '',
                ],
                [
                    'key' => 'to',
                    'label' => __('entry.to_date'),
                    'type' => 'date',
                    'value' => $to ?? '',
                ],
            ],
            sort: $sort,
            direction: $direction,
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

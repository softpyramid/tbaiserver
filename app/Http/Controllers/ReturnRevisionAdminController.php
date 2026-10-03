<?php

namespace App\Http\Controllers;

use App\Models\DailyReturnRevision;
use App\Models\Facility;
use App\Support\ResourceResponse;
use App\Support\ReturnRevisionPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnRevisionAdminController extends Controller
{
    public function index(Request $request, ReturnRevisionPresenter $presenter): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort($request, ['when'], 'when', 'desc');

        $query = DailyReturnRevision::query()
            ->with(['editor', 'dailyReturn.facility.team', 'dailyReturn.facility.city'])
            ->orderBy('changed_at', $direction)
            ->orderBy('id', $direction);

        $facilityId = $request->integer('facility');

        if ($facilityId > 0) {
            $query->whereHas(
                'dailyReturn',
                fn (Builder $dailyReturn) => $dailyReturn->where('facility_id', $facilityId),
            );
        }

        $columns = [
            ['key' => 'when', 'label' => __('entry.column_when')],
            ['key' => 'who', 'label' => __('entry.column_who')],
            ['key' => 'facility', 'label' => __('entry.column_facility')],
            ['key' => 'city', 'label' => __('entry.city')],
            ['key' => 'field', 'label' => __('entry.column_field')],
            ['key' => 'old', 'label' => __('entry.column_old')],
            ['key' => 'new', 'label' => __('entry.column_new')],
        ];

        $expand = function (DailyReturnRevision $revision) use ($presenter): array {
            $lines = $presenter->changeLines($revision);

            if ($lines === []) {
                return [];
            }

            return array_map(fn (array $line): array => [
                'id' => $revision->id.'-'.$line['field'],
                'href' => route('admin.daily-returns.show', $revision->dailyReturn),
                'cells' => [
                    'when' => $line['when'],
                    'who' => $line['who'],
                    'facility' => $revision->dailyReturn->facility->team->name,
                    'city' => $revision->dailyReturn->facility->city->name,
                    'field' => $line['field'],
                    'old' => $line['old'],
                    'new' => $line['new'],
                ],
                'controls' => [],
            ], $lines);
        };

        if (ResourceResponse::wantsCsv($request)) {
            $flat = [];

            foreach ($query->lazy(100) as $revision) {
                foreach ($expand($revision) as $row) {
                    $flat[] = array_values($row['cells']);
                }
            }

            return ResourceResponse::csv('return-revisions.csv', array_column($columns, 'label'), $flat);
        }

        $page = $query->paginate(15)->withQueryString();
        $rows = [];

        foreach ($page->getCollection() as $revision) {
            foreach ($expand($revision) as $row) {
                $rows[] = $row;
            }
        }

        $facilities = Facility::query()->with('team')->get()->sortBy(
            fn (Facility $facility): string => mb_strtolower($facility->team->name),
        );

        return ResourceResponse::page(
            title: __('entry.nav_revisions'),
            columns: $columns,
            rows: $rows,
            filters: [[
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
            ]],
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

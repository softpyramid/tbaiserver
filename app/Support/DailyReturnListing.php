<?php

namespace App\Support;

use App\Models\City;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\Facility;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyReturnListing
{
    public function __construct(
        private DataScope $scope,
        private ReturnRevisionPresenter $revisions,
    ) {}

    public function respond(Request $request, User $user, bool $administration): Response|StreamedResponse
    {
        $query = $this->query($request, $user, $administration);
        [$sort, $direction] = ResourceResponse::sort(
            $request,
            ['date', 'facility', 'city', 'suspects', 'tested', 'positive', 'negative', 'started', 'follow_up', 'tat', 'positivity'],
            'date',
            'desc',
        );
        $this->order($query, $sort, $direction);

        $columns = $this->columns();

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'daily-returns.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (DailyReturn $return): array => array_values($this->cells($return))),
            );
        }

        $page = $query->paginate(15)->withQueryString();
        $rows = $page->getCollection()
            ->map(fn (DailyReturn $return): array => [
                'id' => (string) $return->id,
                'href' => $administration
                    ? route('admin.daily-returns.show', $return)
                    : route('command.returns.show', $return),
                'cells' => $this->cells($return),
                'controls' => [],
            ])
            ->values()
            ->all();

        return ResourceResponse::page(
            title: __('entry.nav_daily_returns'),
            columns: $columns,
            rows: $rows,
            filters: $this->filters($request, $user, $administration),
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

    /**
     * @return Builder<DailyReturn>
     */
    private function query(Request $request, User $user, bool $administration): Builder
    {
        $query = DailyReturn::query()->with([
            'facility.team',
            'facility.city.province',
            'submitter',
            'revisions.editor',
        ]);

        if (! $administration) {
            $facilities = Facility::query();
            $this->scope->constrainFacilities($facilities, $user);
            $query->whereIn('facility_id', $facilities->select('facilities.id'));
        }

        $facilityId = $request->integer('facility');

        if ($facilityId > 0) {
            $query->where('facility_id', $facilityId);
        }

        $cityId = $request->integer('city');

        if ($cityId > 0) {
            $query->whereHas('facility', fn (Builder $facility) => $facility->where('city_id', $cityId));
        }

        $provinceId = $request->integer('province');

        if ($provinceId > 0) {
            $query->whereHas(
                'facility.city',
                fn (Builder $city) => $city->where('province_id', $provinceId),
            );
        }

        $from = ResourceResponse::date($request, 'from');
        $to = ResourceResponse::date($request, 'to');

        if ($from !== null) {
            $query->whereDate('reported_on', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('reported_on', '<=', $to);
        }

        return $query;
    }

    /**
     * @param  Builder<DailyReturn>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function order(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'date') {
            $query->orderBy('reported_on', $direction);

            return;
        }

        if ($sort === 'facility') {
            $query->orderBy(
                Team::query()
                    ->select('teams.name')
                    ->join('facilities', 'facilities.team_id', '=', 'teams.id')
                    ->whereColumn('facilities.id', 'daily_returns.facility_id')
                    ->limit(1),
                $direction,
            );

            return;
        }

        if ($sort === 'city') {
            $query->orderBy(
                City::query()
                    ->select('cities.name')
                    ->join('facilities', 'facilities.city_id', '=', 'cities.id')
                    ->whereColumn('facilities.id', 'daily_returns.facility_id')
                    ->limit(1),
                $direction,
            );

            return;
        }

        if ($sort === 'positivity') {
            if ($direction === 'asc') {
                $query->orderByRaw('case when tested = 0 then null else (positive * 1.0 / tested) end asc');
            } else {
                $query->orderByRaw('case when tested = 0 then null else (positive * 1.0 / tested) end desc');
            }

            return;
        }

        $column = match ($sort) {
            'suspects' => 'suspects',
            'tested' => 'tested',
            'positive' => 'positive',
            'negative' => 'negative',
            'started' => 'started_treatment',
            'follow_up' => 'follow_up',
            'tat' => 'median_tat_days',
            default => 'reported_on',
        };

        $query->orderBy($column, $direction);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function columns(): array
    {
        return [
            ['key' => 'date', 'label' => __('entry.column_date')],
            ['key' => 'facility', 'label' => __('entry.column_facility')],
            ['key' => 'city', 'label' => __('entry.city')],
            ['key' => 'suspects', 'label' => __('entry.column_suspects')],
            ['key' => 'tested', 'label' => __('entry.column_tested')],
            ['key' => 'positive', 'label' => __('entry.column_positive')],
            ['key' => 'negative', 'label' => __('entry.column_negative')],
            ['key' => 'started', 'label' => __('entry.column_started')],
            ['key' => 'follow_up', 'label' => __('entry.column_follow_up')],
            ['key' => 'tat', 'label' => __('entry.column_tat')],
            ['key' => 'positivity', 'label' => __('entry.column_positivity')],
            ['key' => 'edited_by', 'label' => __('entry.column_edited_by')],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function cells(DailyReturn $return): array
    {
        $latest = $return->revisions
            ->sortByDesc(fn (DailyReturnRevision $revision): string => $revision->changed_at->format('Y-m-d H:i:s').sprintf('%010d', $revision->id))
            ->first();

        return [
            'date' => EntryDate::day($return->reported_on),
            'facility' => $return->facility->team->name,
            'city' => $return->facility->city->name,
            'suspects' => (string) $return->suspects,
            'tested' => (string) $return->tested,
            'positive' => (string) $return->positive,
            'negative' => (string) $return->negative,
            'started' => (string) $return->started_treatment,
            'follow_up' => (string) $return->follow_up,
            'tat' => $return->median_tat_days === null
                ? __('entry.not_available')
                : (string) $return->median_tat_days,
            'positivity' => $this->revisions->positivity($return->positive, $return->tested),
            'edited_by' => $latest?->editor === null ? $return->submitter->name : $latest->editor->name,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filters(Request $request, User $user, bool $administration): array
    {
        $facilities = Facility::query()->with('team');

        if (! $administration) {
            $this->scope->constrainFacilities($facilities, $user);
        }

        $cities = City::query()->orderBy('name');
        $provinces = Province::query()->orderBy('name');

        if (! $administration) {
            $this->scope->constrainCities($cities, $user);
            $provinces->whereIn('id', (clone $cities)->reorder()->select('cities.province_id'));
        }

        $facilityOptions = $facilities->get()->sortBy(fn (Facility $facility): string => mb_strtolower($facility->team->name))->map(
            fn (Facility $facility): array => [
                'value' => (string) $facility->id,
                'label' => $facility->team->name,
            ],
        )->values()->all();

        return [
            [
                'key' => 'facility',
                'label' => __('entry.column_facility'),
                'type' => 'select',
                'value' => $request->integer('facility') > 0 ? (string) $request->integer('facility') : '',
                'options' => array_merge([['value' => '', 'label' => __('entry.any')]], $facilityOptions),
            ],
            [
                'key' => 'city',
                'label' => __('entry.city'),
                'type' => 'select',
                'value' => $request->integer('city') > 0 ? (string) $request->integer('city') : '',
                'options' => array_merge(
                    [['value' => '', 'label' => __('entry.any')]],
                    $cities->get(['id', 'name'])->map(fn (City $city): array => [
                        'value' => (string) $city->id,
                        'label' => $city->name,
                    ])->all(),
                ),
            ],
            [
                'key' => 'province',
                'label' => __('entry.province'),
                'type' => 'select',
                'value' => $request->integer('province') > 0 ? (string) $request->integer('province') : '',
                'options' => array_merge(
                    [['value' => '', 'label' => __('entry.any')]],
                    $provinces->get(['id', 'name'])->map(fn (Province $province): array => [
                        'value' => (string) $province->id,
                        'label' => $province->name,
                    ])->all(),
                ),
            ],
            [
                'key' => 'from',
                'label' => __('entry.from_date'),
                'type' => 'date',
                'value' => ResourceResponse::date($request, 'from') ?? '',
            ],
            [
                'key' => 'to',
                'label' => __('entry.to_date'),
                'type' => 'date',
                'value' => ResourceResponse::date($request, 'to') ?? '',
            ],
        ];
    }
}

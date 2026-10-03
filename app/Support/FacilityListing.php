<?php

namespace App\Support;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Models\City;
use App\Models\Facility;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacilityListing
{
    public function __construct(private DataScope $scope) {}

    public function respond(Request $request, User $user, bool $administration): Response|StreamedResponse
    {
        $query = $this->query($request, $user, $administration);
        [$sort, $direction] = ResourceResponse::sort(
            $request,
            ['name', 'city', 'sector', 'kind', 'status'],
            'name',
        );
        $this->order($query, $sort, $direction);

        $columns = [
            ['key' => 'name', 'label' => __('entry.facility_name')],
            ['key' => 'sector', 'label' => __('entry.sector')],
            ['key' => 'kind', 'label' => __('entry.kind')],
            ['key' => 'city', 'label' => __('entry.city')],
            ['key' => 'active', 'label' => __('entry.active')],
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'facilities.csv',
                array_column($columns, 'label'),
                $query->get()->map(fn (Facility $facility): array => array_values($this->cells($facility))),
            );
        }

        $page = $query->paginate(15)->withQueryString();
        $rows = $page->getCollection()
            ->map(fn (Facility $facility): array => [
                'id' => (string) $facility->id,
                'href' => $administration ? route('facilities.edit', $facility->team) : null,
                'cells' => $this->cells($facility),
                'controls' => [],
            ])
            ->values()
            ->all();

        return ResourceResponse::page(
            title: __('entry.nav_facilities'),
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
            createUrl: $administration ? route('facilities.create') : null,
            createLabel: $administration ? ResourceResponse::line('entry.create_facility') : null,
            exportUrl: ResourceResponse::exportUrl($request),
        );
    }

    /**
     * @return Builder<Facility>
     */
    private function query(Request $request, User $user, bool $administration): Builder
    {
        $query = Facility::query()->with(['team', 'city']);

        if (! $administration) {
            $this->scope->constrainFacilities($query, $user);
        }

        $search = trim($request->string('q')->toString());

        if ($search !== '') {
            $query->whereHas('team', function (Builder $team) use ($search): void {
                $team->where('name', 'like', ResourceResponse::like($search));
            });
        }

        $sector = Sector::tryFrom($request->string('sector')->toString());

        if ($sector instanceof Sector) {
            $query->where('sector', $sector);
        }

        $kind = FacilityKind::tryFrom($request->string('kind')->toString());

        if ($kind instanceof FacilityKind) {
            $query->where('kind', $kind);
        }

        $cityId = $request->integer('city');

        if ($cityId > 0) {
            $query->where('city_id', $cityId);
        }

        $active = $request->string('active')->toString();

        if ($active === '1' || $active === '0') {
            $query->where(
                'status',
                $active === '1' ? FacilityStatus::Active : FacilityStatus::Inactive,
            );
        }

        return $query;
    }

    /**
     * @param  Builder<Facility>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function order(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'name') {
            $query->orderBy(
                Team::query()->select('name')->whereColumn('teams.id', 'facilities.team_id'),
                $direction,
            );

            return;
        }

        if ($sort === 'city') {
            $query->orderBy(
                City::query()->select('name')->whereColumn('cities.id', 'facilities.city_id'),
                $direction,
            );

            return;
        }

        $query->orderBy($sort, $direction);
    }

    /**
     * @return array{name: string, sector: string, kind: string, city: string, active: string}
     */
    private function cells(Facility $facility): array
    {
        return [
            'name' => $facility->team->name,
            'sector' => $facility->sector->label(),
            'kind' => $facility->kind->label(),
            'city' => $facility->city->name,
            'active' => $facility->status === FacilityStatus::Active
                ? __('entry.active')
                : __('entry.inactive'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filters(Request $request, User $user, bool $administration): array
    {
        $cities = City::query()->orderBy('name');

        if (! $administration) {
            $this->scope->constrainCities($cities, $user);
        }

        return [
            [
                'key' => 'q',
                'label' => __('entry.search'),
                'type' => 'search',
                'value' => $request->string('q')->toString(),
            ],
            [
                'key' => 'sector',
                'label' => __('entry.sector'),
                'type' => 'select',
                'value' => $request->string('sector')->toString(),
                'options' => $this->options(Sector::options()),
            ],
            [
                'key' => 'kind',
                'label' => __('entry.kind'),
                'type' => 'select',
                'value' => $request->string('kind')->toString(),
                'options' => $this->options(FacilityKind::options()),
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
                'key' => 'active',
                'label' => __('entry.active'),
                'type' => 'select',
                'value' => $request->string('active')->toString(),
                'options' => [
                    ['value' => '', 'label' => __('entry.any')],
                    ['value' => '1', 'label' => __('entry.active')],
                    ['value' => '0', 'label' => __('entry.inactive')],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    private function options(array $options): array
    {
        return array_merge(
            [['value' => '', 'label' => __('entry.any')]],
            $options,
        );
    }
}

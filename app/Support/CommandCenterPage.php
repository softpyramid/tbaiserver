<?php

namespace App\Support;

use App\Enums\Sector;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Command center payloads. Aggregates are computed from daily returns for this request only.
 */
class CommandCenterPage
{
    public function __construct(
        private DataScope $scope,
        private ReturnRevisionPresenter $presenter,
        private CommandReturnQuery $returns,
        private CommandMapData $mapData,
        private CommandAlerts $alerts,
        private CommandDrivers $drivers,
        private CommandIncidence $incidence,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cumulative(Request $request, User $user): array
    {
        $selection = $this->selection($request, $user, null, null);
        $rows = $this->rows($user, $selection);
        $summary = CommandTotals::summarize($rows);

        return [
            ...$this->frame($user, $selection, 'cumulative', showSector: true, showPlace: true),
            'title' => __('entry.cumulative_title'),
            'totals' => $this->totals($summary),
            'kpis' => $this->kpis($summary),
            'cascade' => $this->cascade($summary),
            'months' => CommandTotals::runningMonths($rows, $selection['period']),
            'hasReturns' => $rows !== [],
            'notCollected' => $this->notCollected(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sector(Request $request, User $user, Sector $sector): array
    {
        $selection = $this->selection($request, $user, $sector, null);
        $rows = $this->rows($user, $selection);
        [$sort, $direction] = ResourceResponse::sort(
            $request,
            [
                'district',
                'suspects',
                'tested',
                'positive',
                'positivity',
                'started_treatment',
                'treatment_gap',
                'provisional_tat',
                'active_facilities',
            ],
            'district',
        );
        $districts = $this->sortDistricts(CommandTotals::byDistrict($rows), $sort, $direction);

        return [
            ...$this->frame($user, $selection, $sector->value, showSector: false, showPlace: true),
            'title' => $this->sectorTitle($sector),
            'columns' => $this->districtColumns(),
            'rows' => array_map(fn (array $district): array => $this->districtRow($district, $sector, $selection), $districts),
            'sort' => $sort,
            'direction' => $direction,
            'hasReturns' => $rows !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function district(Request $request, User $user, Sector $sector, City $city): array
    {
        $selection = $this->selection($request, $user, $sector, $city->id);
        $rows = $this->rows($user, $selection);
        $facilities = [];

        foreach (CommandTotals::byFacility($rows) as $group) {
            $summary = CommandTotals::summarize($group['rows']);
            $facilities[] = [
                'id' => (string) $group['facility_id'],
                'name' => $group['name'],
                'cascade' => $this->cascade($summary),
                'months' => CommandTotals::runningMonths($group['rows'], $selection['period']),
            ];
        }

        return [
            ...$this->frame($user, $selection, $sector->value, showSector: false, showPlace: false),
            'title' => $city->name,
            'sectorTitle' => $this->sectorTitle($sector),
            'backUrl' => route('command.sectors.show', [
                'sector' => $sector->value,
                ...$this->placeQuery($selection),
            ]),
            'crumbs' => [
                [
                    'title' => __('entry.nav_group_command'),
                    'href' => route('command.index', $this->placeQuery($selection)),
                ],
                [
                    'title' => $sector->label(),
                    'href' => route('command.sectors.show', [
                        'sector' => $sector->value,
                        ...$this->placeQuery($selection),
                    ]),
                ],
                [
                    'title' => $city->name,
                    'href' => null,
                ],
            ],
            'facilities' => $facilities,
            'hasReturns' => $rows !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function map(Request $request, User $user): array
    {
        $selection = $this->selection($request, $user, null, null);
        $rows = $this->rows($user, $selection);
        $trendPeriod = CommandPeriod::trailingMonths($selection['period']->to, 12);
        $trendSelection = $selection;
        $trendSelection['period'] = $trendPeriod;

        return [
            ...$this->frame($user, $selection, 'map', showSector: true, showPlace: true),
            'title' => __('entry.command_map'),
            ...$this->mapData->present($request, $user, $selection, $rows, $this->rows($user, $trendSelection), $trendPeriod),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function alerts(Request $request, User $user): array
    {
        $selection = $this->selection($request, $user, null, null);

        return [
            ...$this->frame($user, $selection, 'alerts', showSector: true, showPlace: true),
            'title' => __('entry.command_alerts'),
            ...$this->alerts->present($user, $selection),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function drivers(Request $request, User $user): array
    {
        $selection = $this->selection($request, $user, null, null);

        return [
            ...$this->frame($user, $selection, 'drivers', showSector: true, showPlace: true),
            'title' => __('entry.drivers_title'),
            ...$this->drivers->present($user, $selection),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function incidence(Request $request, User $user): array
    {
        $selection = $this->selection($request, $user, null, null);
        $payload = [
            ...$this->frame($user, $selection, 'incidence', showSector: false, showPlace: true),
            'title' => __('entry.incidence_title'),
            ...$this->incidence->present($user, $selection),
        ];
        $payload['show']['district'] = false;
        $payload['show']['sector'] = false;

        return $payload;
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return array<string, mixed>
     */
    private function frame(User $user, array $selection, string $current, bool $showSector, bool $showPlace): array
    {
        return [
            'scope' => $this->scopeLine($user, $selection, $showSector),
            'screens' => $this->screens($selection, $current),
            'filters' => [
                'from' => $selection['period']->from,
                'to' => $selection['period']->to,
                'province' => $selection['provinceId'] === null ? '' : (string) $selection['provinceId'],
                'district' => $selection['cityId'] === null ? '' : (string) $selection['cityId'],
                'sector' => $selection['sector'] === null ? '' : $selection['sector']->value,
            ],
            'options' => [
                'provinces' => $this->provinceOptions($user),
                'districts' => $this->districtOptions($user, $selection['provinceId']),
                'sectors' => $this->sectorOptions(),
            ],
            'show' => [
                'province' => $showPlace,
                'district' => $showPlace,
                'sector' => $showSector,
            ],
            'labels' => [
                'apply' => __('entry.apply'),
                'clear' => __('entry.clear'),
                'from' => __('entry.from_date'),
                'to' => __('entry.to_date'),
                'province' => __('entry.province'),
                'district' => __('entry.column_district'),
                'sector' => __('entry.sector'),
                'empty' => __('entry.no_rows'),
                'graph' => __('entry.view_graph'),
                'table' => __('entry.view_table'),
                'cascade' => __('entry.cascade_heading'),
                'monthly' => __('entry.monthly_running_totals'),
                'sort' => __('entry.sort'),
                'back' => __('entry.command_back_sector'),
                'facilities' => __('entry.command_facilities_heading'),
                'filters' => __('entry.filters'),
            ],
            'links' => $this->resourceLinks($user),
            'tat' => [
                'note' => ProvisionalReportedTat::HELPER,
                'definition' => ProvisionalReportedTat::LABEL,
            ],
        ];
    }

    /**
     * The scope name, plus the sector when the sector filter is narrowing
     * the figures, so the page says what the numbers cover.
     *
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     */
    private function scopeLine(User $user, array $selection, bool $showSector): string
    {
        $scope = $this->scope->label($user);

        if ($showSector && $selection['sector'] instanceof Sector) {
            return $scope.', '.$selection['sector']->label();
        }

        return $scope;
    }

    /**
     * @return array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }
     */
    private function selection(Request $request, User $user, ?Sector $lockedSector, ?int $lockedCityId): array
    {
        return $this->returns->selection($request, $user, $lockedSector, $lockedCityId);
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<CommandReturnRow>
     */
    private function rows(User $user, array $selection): array
    {
        return $this->returns->rows($user, $selection);
    }

    /**
     * @param  array{
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     negative: int,
     *     started_treatment: int,
     *     follow_up: int,
     *     positivity: float|null,
     *     treatment_gap: int,
     *     active_facilities: int,
     *     provisional_tat: float|null
     * }  $summary
     * @return array{
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     negative: int,
     *     started_treatment: int,
     *     follow_up: int,
     *     positivity: float|null,
     *     treatment_gap: int,
     *     active_facilities: int,
     *     provisional_tat: float|null
     * }
     */
    private function totals(array $summary): array
    {
        return $summary;
    }

    /**
     * @param  array{
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     negative: int,
     *     started_treatment: int,
     *     follow_up: int,
     *     positivity: float|null,
     *     treatment_gap: int,
     *     active_facilities: int,
     *     provisional_tat: float|null
     * }  $summary
     * @return list<array{key: string, label: string, value: string}>
     */
    private function kpis(array $summary): array
    {
        return [
            ['key' => 'suspects', 'label' => __('entry.suspects'), 'value' => (string) $summary['suspects']],
            ['key' => 'tested', 'label' => __('entry.tested'), 'value' => (string) $summary['tested']],
            ['key' => 'positive', 'label' => __('entry.positive'), 'value' => (string) $summary['positive']],
            ['key' => 'negative', 'label' => __('entry.negative'), 'value' => (string) $summary['negative']],
            [
                'key' => 'positivity',
                'label' => __('entry.positivity_rate'),
                'value' => $summary['positivity'] === null
                    ? __('entry.positivity_unavailable')
                    : $this->presenter->positivity($summary['positive'], $summary['tested']),
            ],
            ['key' => 'started_treatment', 'label' => __('entry.started_treatment_short'), 'value' => (string) $summary['started_treatment']],
            ['key' => 'treatment_gap', 'label' => __('entry.kpi_treatment_gap'), 'value' => (string) $summary['treatment_gap']],
            ['key' => 'active_facilities', 'label' => __('entry.kpi_active_facilities'), 'value' => (string) $summary['active_facilities']],
            ['key' => 'follow_up', 'label' => __('entry.follow_up'), 'value' => (string) $summary['follow_up']],
        ];
    }

    /**
     * @param  array{suspects: int, tested: int, positive: int, started_treatment: int}  $summary
     * @return list<array{key: string, label: string, value: int}>
     */
    private function cascade(array $summary): array
    {
        return [
            ['key' => 'suspects', 'label' => __('entry.suspects'), 'value' => $summary['suspects']],
            ['key' => 'tested', 'label' => __('entry.tested'), 'value' => $summary['tested']],
            ['key' => 'positive', 'label' => __('entry.positive'), 'value' => $summary['positive']],
            ['key' => 'started_treatment', 'label' => __('entry.started_treatment_short'), 'value' => $summary['started_treatment']],
        ];
    }

    /**
     * Measures the daily form does not collect. No numeric stand-in is returned.
     *
     * @return array{heading: string, items: list<array{key: string, status: string, label: string}>}
     */
    private function notCollected(): array
    {
        $items = [
            ['key' => 'treatment_success', 'status' => 'not_collected', 'label' => __('entry.treatment_success')],
            ['key' => 'lost_to_follow_up', 'status' => 'not_collected', 'label' => __('entry.lost_to_follow_up')],
            ['key' => 'mdr_rr', 'status' => 'not_collected', 'label' => __('entry.mdr_rr')],
        ];

        if (config('tbai.features.ai_tests')) {
            $items[] = ['key' => 'ai_assisted_tests', 'status' => 'not_collected', 'label' => __('entry.ai_assisted_tests')];
        }

        return [
            'heading' => __('entry.not_collected_yet'),
            'items' => $items,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function districtColumns(): array
    {
        return [
            ['key' => 'district', 'label' => __('entry.column_district')],
            ['key' => 'suspects', 'label' => __('entry.column_suspects')],
            ['key' => 'tested', 'label' => __('entry.column_tested')],
            ['key' => 'positive', 'label' => __('entry.column_positive')],
            ['key' => 'positivity', 'label' => __('entry.column_positivity')],
            ['key' => 'started_treatment', 'label' => __('entry.started_treatment_short')],
            ['key' => 'treatment_gap', 'label' => __('entry.column_treatment_gap')],
            ['key' => 'provisional_tat', 'label' => __('entry.column_tat_provisional')],
            ['key' => 'active_facilities', 'label' => __('entry.column_active_facilities')],
        ];
    }

    /**
     * @param  array{
     *     city_id: int,
     *     district: string,
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     started_treatment: int,
     *     positivity: float|null,
     *     treatment_gap: int,
     *     active_facilities: int,
     *     provisional_tat: float|null
     * }  $district
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return array{id: string, href: string, cells: array<string, string>}
     */
    private function districtRow(array $district, Sector $sector, array $selection): array
    {
        return [
            'id' => (string) $district['city_id'],
            'href' => route('command.districts.show', [
                'sector' => $sector->value,
                'city' => $district['city_id'],
                ...$this->placeQuery($selection),
            ]),
            'cells' => [
                'district' => $district['district'],
                'suspects' => (string) $district['suspects'],
                'tested' => (string) $district['tested'],
                'positive' => (string) $district['positive'],
                'positivity' => $district['positivity'] === null
                    ? __('entry.not_available')
                    : $this->presenter->positivity($district['positive'], $district['tested']),
                'started_treatment' => (string) $district['started_treatment'],
                'treatment_gap' => (string) $district['treatment_gap'],
                'provisional_tat' => $this->tatLabel($district['provisional_tat']),
                'active_facilities' => (string) $district['active_facilities'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $districts
     * @return list<array<string, mixed>>
     */
    private function sortDistricts(array $districts, string $sort, string $direction): array
    {
        usort($districts, function (array $left, array $right) use ($sort, $direction): int {
            $primary = match ($sort) {
                'district' => $direction === 'desc'
                    ? strcasecmp((string) $right['district'], (string) $left['district'])
                    : strcasecmp((string) $left['district'], (string) $right['district']),
                'positivity' => $this->compareNullable($this->nullableFloat($left['positivity']), $this->nullableFloat($right['positivity']), $direction),
                'provisional_tat' => $this->compareNullable($this->nullableFloat($left['provisional_tat']), $this->nullableFloat($right['provisional_tat']), $direction),
                'suspects' => $this->compareNumber((int) $left['suspects'], (int) $right['suspects'], $direction),
                'tested' => $this->compareNumber((int) $left['tested'], (int) $right['tested'], $direction),
                'positive' => $this->compareNumber((int) $left['positive'], (int) $right['positive'], $direction),
                'started_treatment' => $this->compareNumber((int) $left['started_treatment'], (int) $right['started_treatment'], $direction),
                'treatment_gap' => $this->compareNumber((int) $left['treatment_gap'], (int) $right['treatment_gap'], $direction),
                'active_facilities' => $this->compareNumber((int) $left['active_facilities'], (int) $right['active_facilities'], $direction),
                default => 0,
            };

            if ($primary !== 0) {
                return $primary;
            }

            return strcasecmp((string) $left['district'], (string) $right['district']);
        });

        return $districts;
    }

    private function compareNumber(int $left, int $right, string $direction): int
    {
        $result = $left <=> $right;

        return $direction === 'desc' ? -$result : $result;
    }

    private function compareNullable(?float $left, ?float $right, string $direction): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $this->compareNumber((int) round($left * 1000), (int) round($right * 1000), $direction);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    private function tatLabel(?float $value): string
    {
        if ($value === null) {
            return ProvisionalReportedTat::INSUFFICIENT;
        }

        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return array<string, int|string>
     */
    private function placeQuery(array $selection): array
    {
        $query = [
            'from' => $selection['period']->from,
            'to' => $selection['period']->to,
        ];

        if ($selection['provinceId'] !== null) {
            $query['province'] = $selection['provinceId'];
        }

        return $query;
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<array{key: string, label: string, href: string, current: bool}>
     */
    private function screens(array $selection, string $current): array
    {
        $shared = $this->placeQuery($selection);

        if ($selection['cityId'] !== null) {
            $shared['district'] = $selection['cityId'];
        }

        $cumulative = $shared;

        if ($selection['sector'] !== null && $current === 'cumulative') {
            $cumulative['sector'] = $selection['sector']->value;
        }

        $mapQuery = $shared;

        if ($selection['sector'] !== null) {
            $mapQuery['sector'] = $selection['sector']->value;
        }

        $incidenceQuery = [
            'from' => $selection['period']->from,
            'to' => $selection['period']->to,
        ];

        if ($selection['provinceId'] !== null) {
            $incidenceQuery['province'] = $selection['provinceId'];
        }

        $screens = [
            [
                'key' => 'cumulative',
                'label' => __('entry.cumulative_title'),
                'href' => route('command.index', $cumulative),
                'current' => $current === 'cumulative',
            ],
            [
                'key' => Sector::Public->value,
                'label' => __('entry.command_public'),
                'href' => route('command.sectors.show', ['sector' => Sector::Public->value, ...$shared]),
                'current' => $current === Sector::Public->value,
            ],
            [
                'key' => Sector::Private->value,
                'label' => __('entry.command_private'),
                'href' => route('command.sectors.show', ['sector' => Sector::Private->value, ...$shared]),
                'current' => $current === Sector::Private->value,
            ],
            [
                'key' => Sector::Ppm->value,
                'label' => __('entry.command_ppm'),
                'href' => route('command.sectors.show', ['sector' => Sector::Ppm->value, ...$shared]),
                'current' => $current === Sector::Ppm->value,
            ],
            [
                'key' => 'drivers',
                'label' => __('entry.drivers_title'),
                'href' => route('command.drivers', $mapQuery),
                'current' => $current === 'drivers',
            ],
            [
                'key' => 'map',
                'label' => __('entry.command_map'),
                'href' => route('command.map', $mapQuery),
                'current' => $current === 'map',
            ],
            [
                'key' => 'alerts',
                'label' => __('entry.command_alerts'),
                'href' => route('command.alerts', $mapQuery),
                'current' => $current === 'alerts',
            ],
            [
                'key' => 'incidence',
                'label' => __('entry.incidence_title'),
                'href' => route('command.incidence', $incidenceQuery),
                'current' => $current === 'incidence',
            ],
        ];

        $hidden = array_keys(array_filter([
            'drivers' => ! config('tbai.features.drivers'),
            'incidence' => ! config('tbai.features.incidence'),
        ]));

        return array_values(array_filter(
            $screens,
            fn (array $screen): bool => ! in_array($screen['key'], $hidden, true),
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function provinceOptions(User $user): array
    {
        $cities = City::query();
        $this->scope->constrainCities($cities, $user);
        $provinceIds = (clone $cities)->pluck('province_id')->unique()->filter()->all();

        $options = Province::query()
            ->whereIn('id', $provinceIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province): array => [
                'value' => (string) $province->id,
                'label' => $province->name,
            ])
            ->all();

        return [
            ['value' => '', 'label' => __('entry.scope_all')],
            ...$options,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function districtOptions(User $user, ?int $provinceId): array
    {
        $cities = City::query()->orderBy('name');
        $this->scope->constrainCities($cities, $user);

        if ($provinceId !== null) {
            $cities->where('province_id', $provinceId);
        }

        $options = $cities->get(['id', 'name'])
            ->map(fn (City $city): array => [
                'value' => (string) $city->id,
                'label' => $city->name,
            ])
            ->all();

        return [
            ['value' => '', 'label' => __('entry.scope_all')],
            ...$options,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sectorOptions(): array
    {
        return [
            ['value' => '', 'label' => __('entry.scope_all')],
            ...Sector::options(),
        ];
    }

    /**
     * @return list<array{href: string, label: string}>
     */
    private function resourceLinks(User $user): array
    {
        return [
            [
                'href' => $user->isTbaiAdmin()
                    ? route('facilities.index')
                    : route('command.facilities'),
                'label' => __('entry.nav_facilities'),
            ],
            [
                'href' => $user->isTbaiAdmin()
                    ? route('admin.daily-returns.index')
                    : route('command.returns'),
                'label' => __('entry.nav_daily_returns'),
            ],
        ];
    }

    private function sectorTitle(Sector $sector): string
    {
        return match ($sector) {
            Sector::Public => __('entry.command_public'),
            Sector::Private => __('entry.command_private'),
            Sector::Ppm => __('entry.command_ppm'),
        };
    }
}

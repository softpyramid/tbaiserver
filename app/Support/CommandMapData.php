<?php

namespace App\Support;

use App\Enums\MapIndicator;
use App\Enums\Sector;
use App\Models\City;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Choropleth figures for the current filters, plus a 12-month trend.
 * Both are summed from daily returns on this request. Nothing is stored.
 */
class CommandMapData
{
    public function __construct(
        private DataScope $scope,
        private ReturnRevisionPresenter $presenter,
    ) {}

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @param  list<CommandReturnRow>  $rows
     * @param  list<CommandReturnRow>  $trendRows
     * @return array<string, mixed>
     */
    public function present(
        Request $request,
        User $user,
        array $selection,
        array $rows,
        array $trendRows,
        CommandPeriod $trendPeriod,
    ): array {
        $indicator = MapIndicator::tryFrom($request->string('indicator')->toString()) ?? MapIndicator::Suspects;
        $byCity = $this->rowsByCity($rows);
        $trendByCity = $this->rowsByCity($trendRows);
        $cities = $this->cities($user, $selection);
        $notificationRates = NotificationRate::districtRates(
            array_map(fn (City $city): int => $city->id, $cities),
            $selection['period']->to,
        );
        $districts = [];
        $hidden = [];

        foreach ($cities as $city) {
            $summary = CommandTotals::summarize($byCity[$city->id] ?? []);
            $summary['notification_rate'] = $notificationRates[$city->id] ?? null;

            // A district with no returns in the period has nothing to show, so it
            // is left off the map and the list. A district picked on purpose stays.
            if (! self::hasFigures($summary) && $selection['cityId'] !== $city->id) {
                $hidden[] = DistrictMatch::key($city->name);

                continue;
            }

            $value = $indicator->read($summary);
            $districts[] = [
                'id' => (string) $city->id,
                'match' => DistrictMatch::key($city->name),
                'name' => $city->name,
                'figures' => [
                    'suspects' => $summary['suspects'],
                    'tested' => $summary['tested'],
                    'positive' => $summary['positive'],
                    'started_treatment' => $summary['started_treatment'],
                    'positivity' => $summary['positivity'],
                    'provisional_tat' => $summary['provisional_tat'],
                    'treatment_gap' => $summary['treatment_gap'],
                    'testing_coverage' => CommandTotals::testingCoverage($summary['tested'], $summary['suspects']),
                    'notification_rate' => $summary['notification_rate'],
                ],
                'lines' => $this->lines($summary),
                'display' => $this->display($indicator, $summary),
                'value' => $value,
                'trend' => CommandTotals::monthBuckets($trendByCity[$city->id] ?? [], $trendPeriod),
            ];
        }

        $selectedId = $selection['cityId'] === null ? '' : (string) $selection['cityId'];
        $knownIds = array_column($districts, 'id');

        if (! in_array($selectedId, $knownIds, true)) {
            $selectedId = '';
        }

        return [
            'boundaries' => __('entry.map_boundaries_indicative'),
            'attribution' => __('entry.map_attribution'),
            'geometryUrl' => asset('geo/sindh-districts.geojson'),
            'indicator' => $indicator->value,
            'indicatorLabel' => $indicator->label(),
            'indicators' => MapIndicator::options(),
            'districts' => $districts,
            'hiddenMatches' => $hidden,
            'hiddenNote' => $hidden === [] ? null : __('entry.map_zero_hidden'),
            'top' => $this->top($districts),
            'selectedId' => $selectedId,
            'trendHeading' => __('entry.map_trend_heading'),
            'topHeading' => __('entry.map_top_heading'),
            'chooseDistrict' => __('entry.map_choose_district'),
            'listHeading' => __('entry.map_list_heading'),
            'indicatorField' => __('entry.map_indicator'),
        ];
    }

    /**
     * @param  array{suspects: int, tested: int, positive: int, started_treatment: int, follow_up: int, negative: int}  $summary
     */
    private static function hasFigures(array $summary): bool
    {
        return $summary['suspects'] > 0
            || $summary['tested'] > 0
            || $summary['positive'] > 0
            || $summary['negative'] > 0
            || $summary['started_treatment'] > 0
            || $summary['follow_up'] > 0;
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<City>
     */
    private function cities(User $user, array $selection): array
    {
        $cities = City::query()->orderBy('name');
        $this->scope->constrainCities($cities, $user);

        if ($selection['provinceId'] !== null && $selection['cityId'] === null) {
            $cities->where('province_id', $selection['provinceId']);
        }

        if ($selection['cityId'] !== null) {
            $cities->whereKey($selection['cityId']);
        }

        return $cities->get()->all();
    }

    /**
     * @param  list<CommandReturnRow>  $rows
     * @return array<int, list<CommandReturnRow>>
     */
    private function rowsByCity(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row->cityId][] = $row;
        }

        return $groups;
    }

    /**
     * @param  list<array{id: string, name: string, display: string, value: int|float|null}>  $districts
     * @return list<array{id: string, name: string, display: string, value: int|float}>
     */
    private function top(array $districts): array
    {
        $ranked = array_values(array_filter(
            $districts,
            fn (array $district): bool => $district['value'] !== null,
        ));

        usort($ranked, function (array $left, array $right): int {
            $value = $this->compareValue($left['value'], $right['value']);

            if ($value !== 0) {
                return $value;
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return array_map(
            fn (array $district): array => [
                'id' => $district['id'],
                'name' => $district['name'],
                'display' => $district['display'],
                'value' => $district['value'],
            ],
            array_slice($ranked, 0, 5),
        );
    }

    private function compareValue(int|float|null $left, int|float|null $right): int
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

        return $right <=> $left;
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
    private function lines(array $summary): array
    {
        return [
            ['key' => 'suspects', 'label' => __('entry.suspects'), 'value' => (string) $summary['suspects']],
            ['key' => 'tested', 'label' => __('entry.tested'), 'value' => (string) $summary['tested']],
            ['key' => 'positive', 'label' => __('entry.positive'), 'value' => (string) $summary['positive']],
            [
                'key' => 'positivity',
                'label' => __('entry.positivity_rate'),
                'value' => $summary['positivity'] === null
                    ? __('entry.positivity_unavailable')
                    : $this->presenter->positivity($summary['positive'], $summary['tested']),
            ],
            ['key' => 'started_treatment', 'label' => __('entry.started_treatment_short'), 'value' => (string) $summary['started_treatment']],
            ['key' => 'treatment_gap', 'label' => __('entry.column_treatment_gap'), 'value' => (string) $summary['treatment_gap']],
            [
                'key' => 'provisional_tat',
                'label' => __('entry.column_tat_provisional'),
                'value' => $this->tatLabel($summary['provisional_tat']),
            ],
            [
                'key' => 'testing_coverage',
                'label' => __('entry.testing_coverage'),
                'value' => $this->coverageLabel($summary['tested'], $summary['suspects']),
            ],
        ];
    }

    /**
     * @param  array{
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     started_treatment: int,
     *     positivity: float|null,
     *     treatment_gap: int,
     *     provisional_tat: float|null
     * }  $summary
     */
    private function display(MapIndicator $indicator, array $summary): string
    {
        return match ($indicator) {
            MapIndicator::Suspects => (string) $summary['suspects'],
            MapIndicator::Positivity => $summary['positivity'] === null
                ? __('entry.positivity_unavailable')
                : $this->presenter->positivity($summary['positive'], $summary['tested']),
            MapIndicator::ProvisionalTat => $this->tatLabel($summary['provisional_tat']),
            MapIndicator::TreatmentGap => (string) $summary['treatment_gap'],
            MapIndicator::TestingCoverage => $this->coverageLabel($summary['tested'], $summary['suspects']),
            MapIndicator::NotificationRate => $summary['notification_rate'] === null
                ? __('entry.population_not_set')
                : number_format($summary['notification_rate'], 1, '.', '').' '.__('entry.notification_per_100k'),
        };
    }

    private function coverageLabel(int $tested, int $suspects): string
    {
        $coverage = CommandTotals::testingCoverage($tested, $suspects);

        if ($coverage === null) {
            return __('entry.coverage_unavailable');
        }

        $percent = round($coverage * 100, 1);
        $formatted = number_format($percent, 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.').'%';
    }

    private function tatLabel(?float $value): string
    {
        if ($value === null) {
            return ProvisionalReportedTat::INSUFFICIENT;
        }

        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

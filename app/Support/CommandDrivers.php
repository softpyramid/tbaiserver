<?php

namespace App\Support;

use App\Enums\DriverCategory;
use App\Enums\MapIndicator;
use App\Enums\PendingDriver;
use App\Enums\Sector;
use App\Models\User;

/**
 * Live driver categories from daily returns. Nothing here is stored.
 *
 * Priority districts are the first three districts in the current scope.
 * Diagnostic bottlenecks: highest provisional median TAT.
 * Unusual positivity: highest positivity (positive divided by tested).
 * Missing patients: highest treatment gap (positive minus started treatment).
 * Testing gaps: lowest testing coverage (tested divided by suspects).
 * Resource allocation: highest suspects.
 * Follow-up risk stays unavailable because lost to follow-up is not collected.
 * Follow-up visits on the daily return are not used as a stand-in.
 */
class CommandDrivers
{
    public function __construct(private CommandReturnQuery $returns) {}

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
    public function present(User $user, array $selection): array
    {
        $districts = CommandTotals::byDistrict($this->returns->rows($user, $selection));

        return [
            'badge' => __('entry.thresholds_pending_programme'),
            'categories' => array_map(
                fn (DriverCategory $category): array => $this->category($category, $districts, $selection),
                DriverCategory::cases(),
            ),
            'pending' => array_map(
                fn (PendingDriver $driver): array => [
                    'key' => $driver->value,
                    'label' => $driver->label(),
                    'status' => __('entry.data_source_pending'),
                ],
                PendingDriver::cases(),
            ),
            'driverLabels' => [
                'actions' => __('entry.suggested_actions'),
                'priority' => __('entry.priority_districts'),
                'unavailable' => __('entry.driver_unavailable'),
                'pending' => __('entry.data_source_pending'),
            ],
        ];
    }

    /**
     * @param  list<array{
     *     city_id: int,
     *     district: string,
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
     * }>  $districts
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     formula: string,
     *     signalLabel: string,
     *     actions: list<string>,
     *     districts: list<array{id: string, name: string, value: string, href: string}>
     * }
     */
    private function category(DriverCategory $category, array $districts, array $selection): array
    {
        $ranked = $this->rank($category, $districts, $selection);

        return [
            'key' => $category->value,
            'label' => $category->label(),
            'status' => $ranked === [] ? 'unavailable' : 'available',
            'formula' => $category->formula(),
            'signalLabel' => $category->signalLabel(),
            'actions' => $category->actions(),
            'districts' => $ranked,
        ];
    }

    /**
     * @param  list<array{
     *     city_id: int,
     *     district: string,
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
     * }>  $districts
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<array{id: string, name: string, value: string, href: string}>
     */
    private function rank(DriverCategory $category, array $districts, array $selection): array
    {
        if ($category === DriverCategory::FollowUpRisk) {
            return [];
        }

        $eligible = [];

        foreach ($districts as $district) {
            $sort = $this->sortValue($category, $district);

            if ($sort === null) {
                continue;
            }

            $eligible[] = [
                'district' => $district,
                'sort' => $sort,
            ];
        }

        usort(
            $eligible,
            function (array $left, array $right) use ($category): int {
                $bySignal = $category === DriverCategory::TestingGaps
                    ? $left['sort'] <=> $right['sort']
                    : $right['sort'] <=> $left['sort'];

                if ($bySignal !== 0) {
                    return $bySignal;
                }

                return strcasecmp($left['district']['district'], $right['district']['district']);
            },
        );

        $indicator = $this->indicator($category);
        $ranked = [];

        foreach (array_slice($eligible, 0, 3) as $item) {
            $district = $item['district'];
            $ranked[] = [
                'id' => (string) $district['city_id'],
                'name' => $district['district'],
                'value' => $this->display($category, $item['sort']),
                'href' => $this->mapHref($selection, $district['city_id'], $indicator),
            ];
        }

        return $ranked;
    }

    /**
     * @param  array{
     *     city_id: int,
     *     district: string,
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
     * }  $district
     */
    private function sortValue(DriverCategory $category, array $district): ?float
    {
        return match ($category) {
            DriverCategory::DiagnosticBottlenecks => $district['provisional_tat'],
            DriverCategory::UnusualPositivity => $district['positivity'],
            DriverCategory::MissingPatients => (float) $district['treatment_gap'],
            DriverCategory::FollowUpRisk => null,
            DriverCategory::TestingGaps => CommandTotals::testingCoverage($district['tested'], $district['suspects']),
            DriverCategory::ResourceAllocation => (float) $district['suspects'],
        };
    }

    private function indicator(DriverCategory $category): string
    {
        return match ($category) {
            DriverCategory::DiagnosticBottlenecks => MapIndicator::ProvisionalTat->value,
            DriverCategory::UnusualPositivity => MapIndicator::Positivity->value,
            DriverCategory::MissingPatients => MapIndicator::TreatmentGap->value,
            DriverCategory::FollowUpRisk => MapIndicator::TreatmentGap->value,
            DriverCategory::TestingGaps => MapIndicator::TestingCoverage->value,
            DriverCategory::ResourceAllocation => MapIndicator::Suspects->value,
        };
    }

    private function display(DriverCategory $category, float $sort): string
    {
        return match ($category) {
            DriverCategory::DiagnosticBottlenecks => $this->number($sort),
            DriverCategory::UnusualPositivity, DriverCategory::TestingGaps => $this->percent($sort),
            DriverCategory::MissingPatients, DriverCategory::ResourceAllocation => (string) (int) $sort,
            DriverCategory::FollowUpRisk => '',
        };
    }

    private function number(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function percent(float $ratio): string
    {
        $formatted = number_format(round($ratio * 100, 1), 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.').'%';
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     */
    private function mapHref(array $selection, int $cityId, string $indicator): string
    {
        $query = [
            'from' => $selection['period']->from,
            'to' => $selection['period']->to,
            'district' => $cityId,
            'indicator' => $indicator,
        ];

        if ($selection['provinceId'] !== null) {
            $query['province'] = $selection['provinceId'];
        }

        if ($selection['sector'] !== null) {
            $query['sector'] = $selection['sector']->value;
        }

        return route('command.map', $query);
    }
}

<?php

namespace App\Support;

use App\Enums\Sector;
use App\Models\City;
use App\Models\IncidenceTarget;
use App\Models\Province;
use App\Models\User;

/**
 * Yearly provincial targets entered by a TB-AI admin.
 * An empty table returns no series.
 */
class CommandIncidence
{
    public function __construct(private DataScope $scope) {}

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
        $canEdit = $user->isTbaiAdmin();
        $rows = $this->rows($user, $selection);
        $targets = [];
        $series = [];

        foreach ($rows as $target) {
            $province = $target->province;

            if ($province === null) {
                continue;
            }

            $value = $this->valueString($target->value);
            $targets[] = [
                'id' => $target->id,
                'provinceId' => (string) $target->province_id,
                'province' => $province->name,
                'year' => $target->year,
                'value' => $value,
            ];
            $series[] = [
                'year' => $target->year,
                'province' => $province->name,
                'value' => $value,
            ];
        }

        return [
            'canEdit' => $canEdit,
            'storeUrl' => $canEdit ? route('command.incidence.store') : null,
            'estimateStoreUrl' => $canEdit ? route('command.incidence.estimates.store') : null,
            'yearOptions' => IncidenceTarget::years(),
            'estimateYearOptions' => range(2020, 2035),
            'estimateAreas' => $canEdit ? [
                ['type' => 'country', 'id' => null, 'label' => __('entry.country')],
                ...array_map(
                    fn (array $province): array => [
                        'type' => 'province',
                        'id' => (int) $province['value'],
                        'label' => $province['label'],
                    ],
                    $this->provinceOptions($user),
                ),
            ] : [],
            'targetProvinces' => $canEdit ? $this->provinceOptions($user) : [],
            'targets' => $targets,
            'series' => $series,
            'empty' => $targets === [] ? __('entry.incidence_empty') : null,
            'incidenceLabels' => [
                'chart' => __('entry.incidence_yearly'),
                'table' => __('entry.incidence_table'),
                'year' => __('entry.incidence_year'),
                'province' => __('entry.province'),
                'value' => __('entry.incidence_value'),
                'unit' => __('entry.incidence_target_unit'),
                'save' => __('entry.save'),
                'add' => __('entry.incidence_add'),
                'note' => __('entry.incidence_note'),
                'estimateAdd' => __('entry.estimate_add'),
                'estimateAreaType' => __('entry.estimate_area_type'),
                'estimateArea' => __('entry.estimate_area'),
                'estimateYear' => __('entry.incidence_year'),
                'estimateYearHint' => __('entry.estimate_year_hint'),
                'estimateRate' => __('entry.estimate_rate'),
                'estimateLower' => __('entry.estimate_lower'),
                'estimateUpper' => __('entry.estimate_upper'),
                'estimateCases' => __('entry.estimate_cases'),
                'estimateSource' => __('entry.estimate_source_label'),
                'estimateMethod' => __('entry.estimate_method_label'),
                'estimateReference' => __('entry.estimate_reference_label'),
            ],
        ];
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<IncidenceTarget>
     */
    private function rows(User $user, array $selection): array
    {
        $ids = $this->allowedProvinceIds($user);

        if ($selection['provinceId'] !== null) {
            $ids = array_values(array_filter(
                $ids,
                fn (int $id): bool => $id === $selection['provinceId'],
            ));
        }

        if ($ids === []) {
            return [];
        }

        $targets = IncidenceTarget::query()
            ->with('province')
            ->whereIn('province_id', $ids)
            ->whereBetween('year', [IncidenceTarget::MIN_YEAR, IncidenceTarget::MAX_YEAR])
            ->whereNull('month')
            ->get()
            ->all();

        usort(
            $targets,
            function (IncidenceTarget $left, IncidenceTarget $right): int {
                $byYear = $left->year <=> $right->year;

                if ($byYear !== 0) {
                    return $byYear;
                }

                return strcasecmp(
                    $left->province->name ?? '',
                    $right->province->name ?? '',
                );
            },
        );

        return $targets;
    }

    /**
     * Provinces the viewer is allowed to see. Admin sees every province.
     * Other command roles see provinces that contain a city in their scope.
     *
     * @return list<int>
     */
    private function allowedProvinceIds(User $user): array
    {
        $ids = [];

        if ($user->isTbaiAdmin()) {
            foreach (Province::query()->orderBy('id')->pluck('id') as $id) {
                if (is_int($id)) {
                    $ids[] = $id;
                }
            }

            return $ids;
        }

        $cities = City::query();
        $this->scope->constrainCities($cities, $user);

        foreach ($cities->pluck('province_id') as $id) {
            if (is_int($id) && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function provinceOptions(User $user): array
    {
        $ids = $this->allowedProvinceIds($user);

        if ($ids === []) {
            return [];
        }

        $options = [];

        foreach (Province::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']) as $province) {
            $options[] = [
                'value' => sprintf('%d', $province->id),
                'label' => $province->name,
            ];
        }

        return $options;
    }

    private function valueString(mixed $value): string
    {
        $formatted = number_format((float) $value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

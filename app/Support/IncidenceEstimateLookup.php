<?php

namespace App\Support;

use App\Enums\AreaType;
use App\Models\City;
use App\Models\Country;
use App\Models\IncidenceEstimate;
use App\Models\Province;

/**
 * Resolves the estimated TB incidence rate for a selection. The card reads a
 * district row when a district is selected, else the province row for the
 * selected or implied province, else the national reference row. The estimate
 * is never divided by a population or fed into a district table or ranking.
 */
final class IncidenceEstimateLookup
{
    /**
     * The estimate that stands for a selection, or null when no row exists.
     *
     * @return array{
     *     rate: float,
     *     lower: float|null,
     *     upper: float|null,
     *     year: int,
     *     source: string,
     *     method: string,
     *     reference_url: string|null,
     *     area_label: string,
     *     scope: string,
     *     note: string|null
     * }|null
     */
    public static function forSelection(?int $provinceId, ?int $cityId, int $year): ?array
    {
        if ($provinceId === null && $cityId === null) {
            $row = self::row(AreaType::Country, null, $year);

            return $row === null ? null : self::present($row, 'national_reference', null);
        }

        $impliedProvinceId = $provinceId;

        if ($cityId !== null) {
            $row = self::row(AreaType::District, $cityId, $year);

            if ($row !== null) {
                return self::present($row, 'own', null);
            }

            $impliedProvinceId = (int) (City::query()->whereKey($cityId)->value('province_id') ?? $impliedProvinceId);
        }

        if ($impliedProvinceId !== null) {
            $row = self::row(AreaType::Province, $impliedProvinceId, $year);

            if ($row !== null) {
                return self::present($row, 'own', null);
            }
        }

        $row = self::row(AreaType::Country, null, $year);

        if ($row === null) {
            return null;
        }

        $note = $cityId !== null
            ? __('entry.estimate_note_district')
            : __('entry.estimate_note_province', [
                'area' => (string) (Province::query()->whereKey($impliedProvinceId)->value('name') ?? ''),
            ]);

        return self::present($row, 'national_reference', $note);
    }

    /**
     * The card rendered on /command and on the command center cumulative
     * screen. No row at all answers "No approved estimate entered".
     *
     * @return array<string, mixed>
     */
    public static function card(?int $provinceId, ?int $cityId, int $year): array
    {
        $estimate = self::forSelection($provinceId, $cityId, $year);

        if ($estimate === null) {
            return [
                'label' => __('entry.incidence_title'),
                'sub' => null,
                'rate' => null,
                'lower' => null,
                'upper' => null,
                'year' => null,
                'area' => null,
                'scope' => null,
                'source' => null,
                'method' => null,
                'reference_url' => null,
                'status' => 'no_estimate',
                'message' => __('entry.estimate_none'),
            ];
        }

        $sub = $estimate['scope'] === 'national_reference'
            ? __('entry.estimate_sub_national', ['year' => $estimate['year']])
            : __('entry.estimate_sub_area', ['area' => $estimate['area_label'], 'year' => $estimate['year']]);

        return [
            'label' => __('entry.incidence_title'),
            'sub' => $sub,
            'rate' => $estimate['rate'],
            'lower' => $estimate['lower'],
            'upper' => $estimate['upper'],
            'year' => $estimate['year'],
            'area' => $estimate['area_label'],
            'scope' => $estimate['scope'],
            'source' => $estimate['source'],
            'method' => $estimate['method'],
            'reference_url' => $estimate['reference_url'],
            'status' => null,
            'message' => $estimate['note'],
        ];
    }

    private static function row(AreaType $type, ?int $areaId, int $year): ?IncidenceEstimate
    {
        return IncidenceEstimate::query()
            ->where('area_type', $type->value)
            ->where('area_id', $areaId)
            ->where('year', '<=', $year)
            ->orderByDesc('year')
            ->first();
    }

    /**
     * @return array{
     *     rate: float,
     *     lower: float|null,
     *     upper: float|null,
     *     year: int,
     *     source: string,
     *     method: string,
     *     reference_url: string|null,
     *     area_label: string,
     *     scope: string,
     *     note: string|null
     * }
     */
    private static function present(IncidenceEstimate $row, string $scope, ?string $note): array
    {
        return [
            'rate' => (float) $row->rate_per_100k,
            'lower' => $row->lower_bound === null ? null : (float) $row->lower_bound,
            'upper' => $row->upper_bound === null ? null : (float) $row->upper_bound,
            'year' => $row->year,
            'source' => $row->source,
            'method' => $row->method,
            'reference_url' => $row->reference_url,
            'area_label' => self::areaLabel($row),
            'scope' => $scope,
            'note' => $note,
        ];
    }

    private static function areaLabel(IncidenceEstimate $row): string
    {
        return match ($row->area_type) {
            AreaType::Country => (string) (Country::query()->value('name') ?? 'Pakistan'),
            AreaType::Province => (string) (Province::query()->whereKey($row->area_id)->value('name') ?? ''),
            AreaType::District => (string) (City::query()->whereKey($row->area_id)->value('name') ?? ''),
        };
    }
}

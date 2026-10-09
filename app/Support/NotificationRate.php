<?php

namespace App\Support;

use App\Enums\AreaType;
use App\Models\City;
use App\Models\Province;
use App\Support\CommandApi\Labels;
use Illuminate\Support\Facades\DB;

/**
 * TB case notification rate: new and relapse cases per 100,000 population.
 * The numerator is new + relapse only; other previously treated and history
 * unknown never count. Areas are always pooled, so a province is not a mean
 * of its districts.
 */
final class NotificationRate
{
    public const PER = 100000;

    /**
     * The card for one area, anchored on the last month of the period.
     *
     * @return array<string, mixed>
     */
    public static function card(AreaType $type, int $areaId, string $to): array
    {
        $month = substr($to, 0, 7);
        $year = (int) substr($to, 0, 4);
        $monthCases = self::cases($type, $areaId, $month, $month);
        $ytdCases = self::cases($type, $areaId, $year.'-01', $month);
        $population = PopulationLookup::forArea($type, $areaId, $year);

        $status = match (true) {
            ! $monthCases['has_data'] => 'no_notification_data',
            $population === null => 'population_missing',
            default => null,
        };

        return [
            'label' => __('entry.notification_rate_title'),
            'sub' => __('entry.notification_rate_sub'),
            'period' => $month,
            'period_label' => Labels::month($month),
            'cases' => $monthCases['has_data'] ? $monthCases['cases'] : null,
            'ytd_cases' => $ytdCases['has_data'] ? $ytdCases['cases'] : null,
            'population' => $population['population'] ?? null,
            'population_year' => $population['year'] ?? null,
            'source' => $population['source'] ?? null,
            'rate' => $monthCases['has_data'] ? self::rate($monthCases['cases'], $population) : null,
            'ytd_rate' => $ytdCases['has_data'] ? self::rate($ytdCases['cases'], $population) : null,
            'status' => $status,
            'message' => self::missingMessage($type, $areaId, $status),
        ];
    }

    /**
     * The rate for a district, for the last month of the period.
     *
     * @param  list<int>  $cityIds
     * @return array<int, float|null>
     */
    public static function districtRates(array $cityIds, string $to): array
    {
        $month = substr($to, 0, 7);
        $year = (int) substr($to, 0, 4);

        if ($cityIds === []) {
            return [];
        }

        $populations = PopulationLookup::forAreas(AreaType::District, $cityIds, $year);
        $cases = self::casesByDistrict($cityIds, $month, $month);
        $rates = [];

        foreach ($cityIds as $id) {
            $rates[$id] = self::rate($cases[$id] ?? 0, $populations[$id] ?? null);
        }

        return $rates;
    }

    /**
     * Why a rate is missing, when it points at a specific area. Provinces name
     * themselves ("Sindh population not set"); a district keeps the generic
     * wording because the placeholder is a district-level PBS gap.
     */
    private static function missingMessage(AreaType $type, int $areaId, ?string $status): ?string
    {
        if ($type !== AreaType::Province || $status !== 'population_missing') {
            return null;
        }

        return __('entry.province_population_not_set', [
            'province' => (string) Province::query()->whereKey($areaId)->value('name'),
        ]);
    }

    /**
     * @return array{cases: int, has_data: bool}
     */
    public static function cases(AreaType $type, int $areaId, string $from, string $to): array
    {
        $query = DB::table('case_notifications as n')
            ->join('facilities as f', 'f.id', '=', 'n.facility_id')
            ->whereBetween('n.month', [$from, $to])
            ->selectRaw('COALESCE(SUM(n.new_cases),0) as new_cases, COALESCE(SUM(n.relapse_cases),0) as relapse_cases, COUNT(*) as record_count');

        if ($type === AreaType::District) {
            $query->where('f.city_id', $areaId);
        } else {
            $query->whereIn('f.city_id', City::query()->where('province_id', $areaId)->select('cities.id'));
        }

        $row = $query->first();
        $counted = (int) $row->new_cases + (int) $row->relapse_cases;

        return ['cases' => $counted, 'has_data' => (int) $row->record_count > 0];
    }

    /**
     * Counted cases per district, keyed by city id. Districts with no rows are absent.
     *
     * @param  list<int>  $cityIds
     * @return array<int, int>
     */
    private static function casesByDistrict(array $cityIds, string $from, string $to): array
    {
        $rows = DB::table('case_notifications as n')
            ->join('facilities as f', 'f.id', '=', 'n.facility_id')
            ->whereIn('f.city_id', $cityIds)
            ->whereBetween('n.month', [$from, $to])
            ->groupBy('f.city_id')
            ->selectRaw('f.city_id as city_id, COALESCE(SUM(n.new_cases),0) as new_cases, COALESCE(SUM(n.relapse_cases),0) as relapse_cases')
            ->get();

        $cases = [];

        foreach ($rows as $row) {
            $cases[(int) $row->city_id] = (int) $row->new_cases + (int) $row->relapse_cases;
        }

        return $cases;
    }

    /**
     * @param  array{population: int, year: int, source: string}|null  $population
     */
    private static function rate(int $cases, ?array $population): ?float
    {
        if ($population === null || $population['population'] <= 0) {
            return null;
        }

        return round($cases / $population['population'] * self::PER, 4);
    }
}

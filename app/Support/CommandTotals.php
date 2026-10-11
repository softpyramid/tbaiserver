<?php

namespace App\Support;

/**
 * Live sums over daily returns. Nothing here is stored.
 */
final class CommandTotals
{
    /**
     * @param  list<CommandReturnRow>  $rows
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
    public static function summarize(array $rows): array
    {
        $suspects = 0;
        $tested = 0;
        $positive = 0;
        $negative = 0;
        $started = 0;
        $followUp = 0;
        $facilityIds = [];
        $tatWeighted = 0.0;
        $tatWeight = 0.0;

        foreach ($rows as $row) {
            $suspects += $row->suspects;
            $tested += $row->tested;
            $positive += $row->positive;
            $negative += $row->negative;
            $started += $row->startedTreatment;
            $followUp += $row->followUp;
            $facilityIds[$row->facilityId] = true;
            $tatWeighted += $row->tatWeighted;
            $tatWeight += $row->tatWeight;
        }

        return [
            'suspects' => $suspects,
            'tested' => $tested,
            'positive' => $positive,
            'negative' => $negative,
            'started_treatment' => $started,
            'follow_up' => $followUp,
            'positivity' => self::positivity($positive, $tested),
            'treatment_gap' => self::treatmentGap($positive, $started),
            'active_facilities' => count($facilityIds),
            'provisional_tat' => $tatWeight > 0 ? $tatWeighted / $tatWeight : null,
        ];
    }

    public static function positivity(int $positive, int $tested): ?float
    {
        if ($tested === 0) {
            return null;
        }

        return $positive / $tested;
    }

    public static function treatmentGap(int $positive, int $startedTreatment): int
    {
        return $positive - $startedTreatment;
    }

    public static function testingCoverage(int $tested, int $suspects): ?float
    {
        if ($suspects === 0) {
            return null;
        }

        return $tested / $suspects;
    }

    /**
     * Cumulative totals through each month in the period. Months with no returns keep the previous total.
     *
     * @param  list<CommandReturnRow>  $rows
     * @return list<array{key: string, label: string, suspects: int, tested: int, positive: int, started_treatment: int}>
     */
    public static function runningMonths(array $rows, CommandPeriod $period): array
    {
        $increments = [];

        foreach ($rows as $row) {
            if ($row->reportedOn < $period->from || $row->reportedOn > $period->to) {
                continue;
            }

            $month = substr($row->reportedOn, 0, 7);
            $bucket = $increments[$month] ?? [
                'suspects' => 0,
                'tested' => 0,
                'positive' => 0,
                'started_treatment' => 0,
            ];
            $bucket['suspects'] += $row->suspects;
            $bucket['tested'] += $row->tested;
            $bucket['positive'] += $row->positive;
            $bucket['started_treatment'] += $row->startedTreatment;
            $increments[$month] = $bucket;
        }

        $running = [
            'suspects' => 0,
            'tested' => 0,
            'positive' => 0,
            'started_treatment' => 0,
        ];
        $points = [];

        foreach ($period->months() as $month) {
            $bucket = $increments[$month] ?? [
                'suspects' => 0,
                'tested' => 0,
                'positive' => 0,
                'started_treatment' => 0,
            ];
            $running['suspects'] += $bucket['suspects'];
            $running['tested'] += $bucket['tested'];
            $running['positive'] += $bucket['positive'];
            $running['started_treatment'] += $bucket['started_treatment'];
            $points[] = [
                'key' => $month,
                'label' => $period->labelForMonth($month),
                'suspects' => $running['suspects'],
                'tested' => $running['tested'],
                'positive' => $running['positive'],
                'started_treatment' => $running['started_treatment'],
            ];
        }

        return $points;
    }

    /**
     * Each month's own totals. Months with no returns stay at zero.
     *
     * @param  list<CommandReturnRow>  $rows
     * @return list<array{key: string, label: string, suspects: int, tested: int, positive: int, started_treatment: int}>
     */
    public static function monthBuckets(array $rows, CommandPeriod $period): array
    {
        $increments = [];

        foreach ($rows as $row) {
            if ($row->reportedOn < $period->from || $row->reportedOn > $period->to) {
                continue;
            }

            $month = substr($row->reportedOn, 0, 7);
            $bucket = $increments[$month] ?? [
                'suspects' => 0,
                'tested' => 0,
                'positive' => 0,
                'started_treatment' => 0,
            ];
            $bucket['suspects'] += $row->suspects;
            $bucket['tested'] += $row->tested;
            $bucket['positive'] += $row->positive;
            $bucket['started_treatment'] += $row->startedTreatment;
            $increments[$month] = $bucket;
        }

        $points = [];

        foreach ($period->months() as $month) {
            $bucket = $increments[$month] ?? [
                'suspects' => 0,
                'tested' => 0,
                'positive' => 0,
                'started_treatment' => 0,
            ];
            $points[] = [
                'key' => $month,
                'label' => $period->labelForMonth($month),
                ...$bucket,
            ];
        }

        return $points;
    }

    /**
     * @param  list<CommandReturnRow>  $rows
     * @return list<array{
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
     * }>
     */
    public static function byDistrict(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row->cityId]['name'] = $row->cityName;
            $groups[$row->cityId]['rows'][] = $row;
        }

        $districts = [];

        foreach ($groups as $cityId => $group) {
            $summary = self::summarize($group['rows']);
            $districts[] = [
                'city_id' => (int) $cityId,
                'district' => $group['name'],
                ...$summary,
            ];
        }

        return $districts;
    }

    /**
     * @param  list<CommandReturnRow>  $rows
     * @return list<array{
     *     facility_id: int,
     *     name: string,
     *     rows: list<CommandReturnRow>
     * }>
     */
    public static function byFacility(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row->facilityId]['name'] = $row->facilityName;
            $groups[$row->facilityId]['rows'][] = $row;
        }

        $facilities = [];

        foreach ($groups as $facilityId => $group) {
            $facilities[] = [
                'facility_id' => (int) $facilityId,
                'name' => $group['name'],
                'rows' => $group['rows'],
            ];
        }

        usort(
            $facilities,
            fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']),
        );

        return $facilities;
    }
}

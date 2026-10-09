<?php

namespace App\Support;

use App\Enums\AreaType;
use App\Models\PopulationDenominator;

/**
 * Resolves the population a rate is measured against. An exact year wins;
 * otherwise the latest earlier year stands in, so a rate is never silently
 * divided by a figure from a later year.
 */
final class PopulationLookup
{
    /**
     * @return array{population: int, year: int, source: string}|null
     */
    public static function forArea(AreaType $type, int $areaId, int $year): ?array
    {
        $row = PopulationDenominator::query()
            ->where('area_type', $type)
            ->where('area_id', $areaId)
            ->where('year', '<=', $year)
            ->orderByDesc('year')
            ->first(['population', 'year', 'source']);

        return $row === null ? null : self::shape($row->population, $row->year, $row->source);
    }

    /**
     * Many areas at once, each resolved to its latest year at or before $year.
     *
     * @param  list<int>  $areaIds
     * @return array<int, array{population: int, year: int, source: string}>
     */
    public static function forAreas(AreaType $type, array $areaIds, int $year): array
    {
        if ($areaIds === []) {
            return [];
        }

        $rows = PopulationDenominator::query()
            ->where('area_type', $type)
            ->whereIn('area_id', $areaIds)
            ->where('year', '<=', $year)
            ->orderBy('area_id')
            ->orderByDesc('year')
            ->get(['area_id', 'population', 'year', 'source']);

        $found = [];

        foreach ($rows as $row) {
            $id = (int) $row->area_id;

            // Rows are ordered newest first per area, so the first one wins.
            if (! isset($found[$id])) {
                $found[$id] = self::shape($row->population, $row->year, $row->source);
            }
        }

        return $found;
    }

    /**
     * @return array{population: int, year: int, source: string}
     */
    private static function shape(mixed $population, mixed $year, mixed $source): array
    {
        return [
            'population' => (int) $population,
            'year' => (int) $year,
            'source' => (string) $source,
        ];
    }
}

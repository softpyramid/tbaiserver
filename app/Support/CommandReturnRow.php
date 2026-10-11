<?php

namespace App\Support;

/**
 * Facility totals for one month inside the viewer's scope and filters.
 * Counts are sums. Turnaround is the weighted sum, not a single median.
 */
final readonly class CommandReturnRow
{
    public function __construct(
        public int $facilityId,
        public string $facilityName,
        public int $cityId,
        public string $cityName,
        public string $reportedOn,
        public int $suspects,
        public int $tested,
        public int $positive,
        public int $negative,
        public int $startedTreatment,
        public int $followUp,
        public float $tatWeighted,
        public float $tatWeight,
        public int $returnCount,
    ) {}
}

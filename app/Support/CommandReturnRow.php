<?php

namespace App\Support;

/**
 * One daily return, already limited to the viewer's scope and filters.
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
        public ?float $medianTatDays,
    ) {}
}

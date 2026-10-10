<?php

namespace App\Support\CommandApi;

use App\Models\IncidenceTarget;
use App\Models\Province;
use App\Support\EntryDate;
use App\Support\IncidenceEstimateLookup;

final class IncidenceBuilder
{
    /**
     * Yearly estimates and planning targets for one province. Estimates come
     * from incidence_estimates (the province row, else the national reference);
     * targets come from incidence_targets. incidence_monthly is not read.
     *
     * @return array<string, mixed>
     */
    public function build(Province $province): array
    {
        $year = (int) EntryDate::now()->format('Y');
        $targets = IncidenceTarget::query()
            ->where('province_id', $province->id)
            ->whereNull('month')
            ->get()
            ->keyBy('year');
        $yearly = [];

        foreach (range(IncidenceTarget::MIN_YEAR, IncidenceTarget::MAX_YEAR) as $estimateYear) {
            $estimate = IncidenceEstimateLookup::forSelection($province->id, null, $estimateYear);
            $target = $targets->get($estimateYear);

            $yearly[] = [
                'year' => $estimateYear,
                'estimate' => $estimate === null ? null : $estimate['rate'],
                'estimate_scope' => $estimate === null ? null : $estimate['scope'],
                'target' => $target === null ? null : $this->number($target->value),
            ];
        }

        return [
            'yearly' => $yearly,
            'estimate' => IncidenceEstimateLookup::forSelection($province->id, null, $year),
            'defaults' => [
                'year' => max(IncidenceTarget::MIN_YEAR, min(IncidenceTarget::MAX_YEAR, $year)),
            ],
        ];
    }

    private function number(mixed $value): int|float
    {
        $float = (float) $value;

        return floor($float) === $float ? (int) $float : round($float, 2);
    }
}

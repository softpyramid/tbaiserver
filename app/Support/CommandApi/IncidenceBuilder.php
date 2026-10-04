<?php

namespace App\Support\CommandApi;

use App\Models\IncidenceTarget;
use App\Models\Province;
use App\Support\EntryDate;
use Illuminate\Support\Facades\DB;

final class IncidenceBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Province $province): array
    {
        $now = EntryDate::now();
        $year = (int) $now->format('Y');
        $monthly = DB::table('incidence_monthly')
            ->where('province_id', $province->id)
            ->where('month', 'like', $year.'-%')
            ->pluck('value', 'month');
        $months = [];

        for ($m = 1; $m <= (int) $now->format('n'); $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $value = $monthly->get($key);
            $months[] = ['month' => $key, 'label' => Labels::month($key), 'value' => $value === null ? null : $this->number($value)];
        }

        $targets = IncidenceTarget::query()
            ->where('province_id', $province->id)
            ->whereNull('month')
            ->orderBy('year')
            ->get();
        $latest = null;

        foreach ($months as $point) {
            $latest = $point['value'] !== null ? $point['month'] : $latest;
        }

        return [
            'monthly' => $months,
            'yearly' => $targets->map(fn (IncidenceTarget $t): array => [
                'year' => $t->year,
                'value' => $this->number($t->value),
                'kind' => 'target',
            ])->values()->all(),
            'defaults' => [
                'month' => $latest ?? ($months[count($months) - 1]['month'] ?? null),
                'year' => max(IncidenceTarget::MIN_YEAR, min(IncidenceTarget::MAX_YEAR, $year)),
                'monthly_chart' => 'bar',
            ],
        ];
    }

    private function number(mixed $value): int|float
    {
        $float = (float) $value;

        return floor($float) === $float ? (int) $float : round($float, 2);
    }
}

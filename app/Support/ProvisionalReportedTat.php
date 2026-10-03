<?php

namespace App\Support;

use App\Models\DailyReturn;
use Illuminate\Support\Collection;

class ProvisionalReportedTat
{
    public const LABEL = 'Average of reported median TAT (days), weighted by tested count';

    /**
     * Provisional stand-in until the official TAT formula arrives.
     * Rows with no reported median, and rows with a median but zero tested, add no weight.
     *
     * @param  Collection<int, DailyReturn>|iterable<DailyReturn>  $returns
     */
    public static function average(iterable $returns): ?float
    {
        $weighted = 0.0;
        $weight = 0;

        foreach ($returns as $return) {
            if ($return->median_tat_days === null || $return->median_tat_days === '' || $return->tested === 0) {
                continue;
            }

            $tested = (int) $return->tested;
            $weighted += (float) $return->median_tat_days * $tested;
            $weight += $tested;
        }

        if ($weight === 0) {
            return null;
        }

        return $weighted / $weight;
    }
}

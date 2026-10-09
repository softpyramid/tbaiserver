<?php

namespace App\Support\CommandApi;

/**
 * How positivity moved between two comparable periods. Positivity is itself a
 * percentage, so the movement is a difference in percentage points (pp), never
 * a relative change. Either period having no tested returns means there is
 * nothing to compare.
 */
final class PositivityChange
{
    public static function label(int $positive, int $tested, int $previousPositive, int $previousTested): string
    {
        if ($tested <= 0 || $previousTested <= 0) {
            return __('entry.no_comparison');
        }

        $diff = $positive / $tested * 100 - $previousPositive / $previousTested * 100;

        return ($diff >= 0 ? '+' : '-').number_format(abs($diff), 1).' pp';
    }
}

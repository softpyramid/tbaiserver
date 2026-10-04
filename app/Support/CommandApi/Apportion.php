<?php

namespace App\Support\CommandApi;

/**
 * Splits a whole number across slots in proportion to weights, the largest
 * remainder getting the spare units, so the parts add up to the whole.
 */
final class Apportion
{
    /**
     * @param  list<int|float>  $weights
     * @return list<int>
     */
    public static function split(int $total, array $weights): array
    {
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $sum = array_sum($weights);

        if ($sum <= 0) {
            $weights = array_fill(0, $count, 1);
            $sum = $count;
        }

        if ($total <= 0) {
            return array_fill(0, $count, 0);
        }

        $parts = [];
        $fractions = [];

        foreach ($weights as $index => $weight) {
            $raw = $total * $weight / $sum;
            $parts[$index] = (int) floor($raw);
            $fractions[] = [$raw - $parts[$index], $index];
        }

        usort($fractions, fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        foreach (array_slice($fractions, 0, max(0, $total - array_sum($parts))) as [, $index]) {
            $parts[$index]++;
        }

        return array_values($parts);
    }
}

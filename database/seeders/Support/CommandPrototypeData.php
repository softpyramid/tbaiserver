<?php

namespace Database\Seeders\Support;

/**
 * The founder prototype's own deterministic generator, ported to PHP.
 *
 * Every figure derives from fixed arithmetic (a sine hash, no random calls),
 * so two runs give the same numbers. The "All" dataset is the programme
 * headline. The three sector datasets are scaled together so that, across
 * the 87 district-sector cells, Public + Private + PPM adds up to All exactly.
 */
final class CommandPrototypeData
{
    /**
     * name, longitude, latitude, weight. The prototype's SITES.
     *
     * @var list<array{0: string, 1: float|int, 2: float|int, 3: float|int}>
     */
    public const SITES = [
        ['Jacobabad', 68.44, 28.28, 1], ['Kashmore', 69.58, 28.43, 1], ['Shikarpur', 68.64, 27.96, .8],
        ['Ghotki', 69.32, 28, 1.1], ['Sukkur', 68.86, 27.7, 1.1], ['Larkana', 68.21, 27.56, 1.2],
        ['Kambar Shahdadkot', 67.99, 27.59, 1], ['Khairpur', 68.76, 27.53, 1.3], ['Naushahro Feroze', 68.12, 26.84, 1],
        ['Dadu', 67.78, 26.73, 1.2], ['Shaheed Benazirabad', 68.41, 26.25, 1.3], ['Sanghar', 68.95, 26.05, 1.6],
        ['Jamshoro', 68.28, 25.5, .9], ['Matiari', 68.45, 25.65, .6], ['Hyderabad', 68.37, 25.3, 2.2],
        ['Tando Allahyar', 68.72, 25.46, .7], ['Tando Muhammad Khan', 68.54, 25.05, .6], ['Mirpurkhas', 69.01, 25.53, 1.3],
        ['Umerkot', 69.74, 25.36, 1.1], ['Tharparkar', 69.8, 24.7, 1.5], ['Badin', 68.84, 24.66, 1.3],
        ['Sujawal', 68.07, 24.6, .7], ['Thatta', 67.92, 24.95, .9], ['Malir', 67.35, 25.1, 1.6],
        ['Karachi East', 67.12, 24.93, 3.2], ['Karachi Central', 67.03, 24.97, 3.4], ['Karachi South', 67.02, 24.86, 2.3],
        ['Karachi West', 66.9, 25, 3.4], ['Korangi', 67.15, 24.8, 2.2],
    ];

    /** Running-total weights per month, JAN to OCT. */
    public const MONTH_WEIGHTS = [.06, .07, .08, .09, .1, .11, .11, .12, .13, .13];

    public const SECTOR_FACTOR = ['all' => 1.0, 'public' => .55, 'private' => .3, 'ppm' => .15];

    public const SECTOR_ORDER = ['all' => 0, 'public' => 1, 'private' => 2, 'ppm' => 3];

    /** JS Math.round: halves go up. */
    public static function round(float $value): int
    {
        return (int) floor($value + 0.5);
    }

    public static function rnd(float $seed, float $k): float
    {
        $x = sin($seed * 12.9898 + $k * 78.233) * 43758.5453;

        return $x - floor($x);
    }

    /**
     * Largest remainder split of an integer by weights.
     *
     * @param  array<int, float|int>  $weights
     * @return list<int>
     */
    public static function alloc(int $total, array $weights): array
    {
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $sum = array_sum($weights);

        if ($sum <= 0 || $total <= 0) {
            return array_fill(0, $count, 0);
        }

        $floor = [];
        $fractions = [];

        foreach ($weights as $index => $weight) {
            $raw = $total * $weight / $sum;
            $floor[$index] = (int) floor($raw);
            $fractions[] = [$raw - $floor[$index], $index];
        }

        $remainder = $total - array_sum($floor);
        usort($fractions, fn (array $a, array $b): int => $b[0] <=> $a[0]);

        foreach (array_slice($fractions, 0, max(0, $remainder)) as $pair) {
            $floor[$pair[1]]++;
        }

        return array_values($floor);
    }

    /**
     * One sector's raw prototype dataset, one entry per district (SITES order).
     *
     * @return list<array<string, float|int|string>>
     */
    public static function dataset(string $sector): array
    {
        $rows = [];

        foreach (self::SITES as $i => $site) {
            $seed = $i + 1 + self::SECTOR_ORDER[$sector] * .37;
            $r = fn (int $k): float => self::rnd($seed, $k);
            $sus = self::round($site[3] * 4200 * self::SECTOR_FACTOR[$sector] * (.8 + $r(1)));
            $tested = self::round($sus * (.78 + .2 * $r(2)));
            $rate = 8 + $r(3) * 14;
            $pos = self::round($tested * $rate / 100);
            $st = self::round($pos * (.82 + .16 * $r(4)));

            $rows[] = [
                'id' => $i,
                'name' => $site[0],
                'sus' => $sus,
                'tested' => $tested,
                'pos' => $pos,
                'st' => $st,
                'su' => 78 + $r(5) * 16,
                'ltfu' => 3 + $r(6) * 9,
                'tat' => 1.2 + $r(7) * 3,
                'mdr' => self::round($pos * .03 * (.5 + $r(8))),
                'ai' => self::round($tested * (.1 + .5 * $r(9))),
                'bmu' => max(2, self::round($site[3] * 3 * (.6 + $r(10)))),
            ];
        }

        return $rows;
    }

    /**
     * The per district, per sector targets that add up to the All dataset.
     *
     * Each sector keeps the prototype's own sector table. The counts of all
     * 87 cells (29 districts times three sectors) are then scaled together,
     * metric by metric, so that they sum to the All headline exactly (the
     * prototype's sector tables add up to about 0.5 percent more than its All
     * dataset). Rates are the sector table's own, moved by one constant per rate
     * so the weighted programme rate equals the All headline (the prototype's
     * All and sector tables disagree on rates). The result is cached.
     *
     * @return array<string, list<array<string, float|int|string>>>
     */
    public static function targets(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $all = self::dataset('all');
        $order = ['public', 'private', 'ppm'];
        $sectors = [];

        foreach ($order as $sector) {
            $sectors[$sector] = self::dataset($sector);
        }

        $cache = ['public' => [], 'private' => [], 'ppm' => []];
        $cells = count($all);

        foreach (['sus', 'tested', 'pos', 'st', 'mdr', 'ai'] as $metric) {
            $weights = [];

            foreach ($order as $sector) {
                foreach ($sectors[$sector] as $row) {
                    $weights[] = (float) $row[$metric];
                }
            }

            $total = (int) array_sum(array_map(fn (array $row): int => (int) $row[$metric], $all));
            $scaled = self::alloc($total, $weights);

            foreach ($order as $position => $sector) {
                foreach ($sectors[$sector] as $i => $row) {
                    $sectors[$sector][$i][$metric] = $scaled[$position * $cells + $i];
                }
            }
        }

        // One shift per rate, the same for every cell, so that the programme
        // rate (weighted by tests for turnaround, by treatment starts for
        // outcomes) equals the prototype's All headline. The sectors keep
        // their differences from each other.
        $rates = static function (array $shift) use ($order, $sectors): array {
            $acc = ['tat' => [0.0, 0.0], 'su' => [0.0, 0.0], 'ltfu' => [0.0, 0.0]];

            foreach ($order as $sector) {
                foreach ($sectors[$sector] as $row) {
                    [$tat, $su, $ltfu] = self::shifted($row, $shift);
                    $acc['tat'][0] += $tat * (int) $row['tested'];
                    $acc['tat'][1] += (int) $row['tested'];
                    $acc['su'][0] += $su * (int) $row['st'];
                    $acc['su'][1] += (int) $row['st'];
                    $acc['ltfu'][0] += $ltfu * (int) $row['st'];
                    $acc['ltfu'][1] += (int) $row['st'];
                }
            }

            return array_map(fn (array $a): float => $a[1] > 0 ? $a[0] / $a[1] : 0.0, $acc);
        };

        // The prototype shows 3.1 days as its turnaround headline (a median of
        // district values), so that is the target; the other two are the means
        // of the All dataset.
        $target = [
            'tat' => 3.077,
            'su' => array_sum(array_map(fn (array $row): float => (float) $row['su'], $all)) / $cells,
            'ltfu' => array_sum(array_map(fn (array $row): float => (float) $row['ltfu'], $all)) / $cells,
        ];
        $shift = ['tat' => 0.0, 'su' => 0.0, 'ltfu' => 0.0];

        // The clamps in shifted() make the mean move less than the shift, so
        // walk towards the target.
        for ($i = 0; $i < 12; $i++) {
            $now = $rates($shift);

            foreach ($shift as $rate => $value) {
                $shift[$rate] = $value + $target[$rate] - $now[$rate];
            }
        }

        foreach ($order as $sector) {
            foreach ($sectors[$sector] as $row) {
                [$row['tat'], $row['su'], $row['ltfu']] = self::shifted($row, $shift);

                // Scaling each metric on its own can, in a very small cell, put
                // a later step of the pathway above an earlier one.
                $row['tested'] = min((int) $row['tested'], (int) $row['sus']);
                $row['pos'] = min((int) $row['pos'], (int) $row['tested']);
                $row['st'] = min((int) $row['st'], (int) $row['pos']);
                $cache[$sector][] = $row;
            }
        }

        return $cache;
    }

    /**
     * One cell's turnaround, success and loss rates after the programme shift.
     * Success and loss can never add up to more than 97 percent.
     *
     * @param  array<string, float|int|string>  $row
     * @param  array<string, float>  $shift
     * @return array{0: float, 1: float, 2: float}
     */
    private static function shifted(array $row, array $shift): array
    {
        $ltfu = max(.5, (float) $row['ltfu'] + $shift['ltfu']);
        $su = min(97.0 - $ltfu, (float) $row['su'] + $shift['su']);

        return [max(.5, (float) $row['tat'] + $shift['tat']), $su, $ltfu];
    }

    /**
     * BMU split of one district sector, the prototype's bmuData() with the
     * totals and the BMU count open to override.
     *
     * @param  array<string, float|int|string>  $d
     * @return list<array<string, mixed>>
     */
    public static function bmus(string $sector, array $d, int $count): array
    {
        $seed = (int) $d['id'] * 7 + self::SECTOR_ORDER[$sector] * 131;
        $weights = [];

        for ($j = 0; $j < $count; $j++) {
            $weights[] = .5 + self::rnd($seed + $j, 1);
        }

        $make = function (int $total, int $k) use ($weights, $seed): array {
            $w = [];

            foreach ($weights as $j => $x) {
                $w[] = $x * (.85 + .3 * self::rnd($seed + $j, $k));
            }

            return self::alloc($total, $w);
        };

        $sus = $make((int) $d['sus'], 2);
        $tested = $make((int) $d['tested'], 3);
        $pos = $make((int) $d['pos'], 4);
        $started = self::alloc((int) $d['st'], $pos);
        $mdr = self::alloc((int) $d['mdr'], $weights);
        $ai = self::alloc((int) $d['ai'], $tested);
        $out = [];

        // Each month's jitter is scaled to average 1 across the district's BMUs,
        // so the district curve keeps the prototype's month weights while each
        // BMU still gets its own lively line.
        $jitterTested = [];
        $jitterPositive = [];

        foreach (self::MONTH_WEIGHTS as $m => $weight) {
            $jt = [];
            $jp = [];

            for ($j = 0; $j < $count; $j++) {
                $jt[$j] = .85 + .3 * self::rnd($seed + $j * 13 + $m, 8);
                $jp[$j] = .85 + .3 * self::rnd($seed + $j * 17 + $m, 9);
            }

            $meanTested = array_sum($jt) / max(1, $count);
            $meanPositive = array_sum($jp) / max(1, $count);

            for ($j = 0; $j < $count; $j++) {
                $jitterTested[$j][$m] = $weight * $jt[$j] / $meanTested;
                $jitterPositive[$j][$m] = $weight * $jp[$j] / $meanPositive;
            }
        }

        // Per BMU rate multipliers, scaled so the district rate holds when
        // weighted by tests (turnaround) or treatment starts (outcomes).
        $multipliers = ['su' => [], 'ltfu' => [], 'tat' => []];
        for ($j = 0; $j < $count; $j++) {
            $multipliers['su'][$j] = .92 + .16 * self::rnd($seed + $j, 5);
            $multipliers['ltfu'][$j] = .7 + .6 * self::rnd($seed + $j, 6);
            $multipliers['tat'][$j] = .75 + .5 * self::rnd($seed + $j, 7);
        }
        $norm = [];
        foreach (['su' => $started, 'ltfu' => $started, 'tat' => $tested] as $rate => $weights) {
            $sum = array_sum($weights);
            $norm[$rate] = $sum > 0 ? array_sum(array_map(fn (int $w, float $m): float => $w * $m, $weights, $multipliers[$rate])) / $sum : 1.0;
            $norm[$rate] = $norm[$rate] > 0 ? $norm[$rate] : 1.0;
        }

        for ($j = 0; $j < $count; $j++) {
            $monthTested = $jitterTested[$j];
            $monthPositive = $jitterPositive[$j];

            $out[] = [
                'ordinal' => $j + 1,
                'sus' => $sus[$j],
                'tested' => $tested[$j],
                'pos' => $pos[$j],
                'st' => $started[$j],
                'mdr' => $mdr[$j],
                'ai' => $ai[$j],
                'su' => min(99.0, (float) $d['su'] * $multipliers['su'][$j] / $norm['su']),
                'ltfu' => (float) $d['ltfu'] * $multipliers['ltfu'][$j] / $norm['ltfu'],
                'tat' => (float) $d['tat'] * $multipliers['tat'][$j] / $norm['tat'],
                'month_tested_weights' => $monthTested,
                'month_positive_weights' => $monthPositive,
            ];
        }

        return $out;
    }
}

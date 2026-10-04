<?php

namespace Database\Seeders\Support;

/**
 * The founder prototype's own deterministic generator, ported to PHP.
 *
 * Every figure derives from fixed arithmetic (a sine hash, no random calls),
 * so two runs give the same numbers. The "All" dataset is the programme
 * headline. The three sector datasets are scaled so that, district by district,
 * Public + Private + PPM adds up to All exactly.
 */
final class CommandPrototypeData
{
    /**
     * name, longitude, latitude, weight. The prototype's SITES.
     *
     * @var list<array{0: string, 1: float, 2: float|int, 3: float|int}>
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
     * Counts are split by the prototype's own sector shares. Rates come from
     * the sector dataset. The result is cached for the process.
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
        $sectors = ['public' => self::dataset('public'), 'private' => self::dataset('private'), 'ppm' => self::dataset('ppm')];
        $cache = ['public' => [], 'private' => [], 'ppm' => []];

        foreach ($all as $i => $total) {
            $split = [];

            foreach (['sus', 'tested', 'pos', 'mdr', 'ai'] as $metric) {
                $split[$metric] = self::alloc(
                    (int) $total[$metric],
                    [(float) $sectors['public'][$i][$metric], (float) $sectors['private'][$i][$metric], (float) $sectors['ppm'][$i][$metric]],
                );
            }

            $split['st'] = self::alloc((int) $total['st'], $split['pos']);

            foreach (array_keys($cache) as $position => $sector) {
                $row = $sectors[$sector][$i];

                foreach (['sus', 'tested', 'pos', 'st', 'mdr', 'ai'] as $metric) {
                    $row[$metric] = $split[$metric][$position];
                }

                $cache[$sector][] = $row;
            }
        }

        return $cache;
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

        for ($j = 0; $j < $count; $j++) {
            $monthTested = [];
            $monthPositive = [];

            foreach (self::MONTH_WEIGHTS as $m => $weight) {
                $monthTested[] = $weight * (.85 + .3 * self::rnd($seed + $j * 13 + $m, 8));
                $monthPositive[] = $weight * (.85 + .3 * self::rnd($seed + $j * 17 + $m, 9));
            }

            $out[] = [
                'ordinal' => $j + 1,
                'sus' => $sus[$j],
                'tested' => $tested[$j],
                'pos' => $pos[$j],
                'st' => $started[$j],
                'mdr' => $mdr[$j],
                'ai' => $ai[$j],
                'su' => min(99.0, (float) $d['su'] * (.92 + .16 * self::rnd($seed + $j, 5))),
                'ltfu' => (float) $d['ltfu'] * (.7 + .6 * self::rnd($seed + $j, 6)),
                'tat' => (float) $d['tat'] * (.75 + .5 * self::rnd($seed + $j, 7)),
                'month_tested_weights' => $monthTested,
                'month_positive_weights' => $monthPositive,
            ];
        }

        return $out;
    }
}

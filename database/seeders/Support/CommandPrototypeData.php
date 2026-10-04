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
     * Largest remainder split of an integer by weights, with a ceiling per slot.
     * What a slot cannot take goes to the others, in proportion to their weights.
     *
     * @param  list<float|int>  $weights
     * @param  list<int>  $caps
     * @return list<int>
     */
    public static function allocCapped(int $total, array $weights, array $caps): array
    {
        $out = array_fill(0, count($weights), 0);
        $open = array_keys($weights);
        $left = $total;

        while ($left > 0 && $open !== []) {
            $share = self::alloc($left, array_map(fn (int $i): float => max(0.0, (float) $weights[$i]) + 1e-9, $open));
            $over = false;

            foreach ($open as $k => $i) {
                $room = $caps[$i] - $out[$i];

                if ($share[$k] > $room) {
                    $out[$i] += $room;
                    $left -= $room;
                    $over = true;
                    unset($open[$k]);
                }
            }

            if (! $over) {
                foreach ($open as $k => $i) {
                    $out[$i] += $share[$k];
                }

                $left = 0;
            }

            $open = array_values($open);
        }

        return array_values($out);
    }

    /**
     * The per district, per sector targets.
     *
     * Every district's All row is the prototype's own, to the unit: the three
     * sector cells of a district add up to it exactly (suspects, tested,
     * positive, started, MDR/RR, AI tests), and the outcome cohorts and the
     * turnaround are set so that the district's success, loss to follow-up and
     * turnaround read as in the All table. The split between the sectors follows
     * the shares of the prototype's own sector tables, capped so a later step of
     * the pathway never exceeds an earlier one. The prototype's sector tables
     * and its All table were generated separately and disagree, so a sector row
     * differs from the prototype's sector row by that district's disagreement.
     *
     * Treatment outcome cohorts are the patients who started treatment a year
     * earlier, so they are sized per district (about the started count times a
     * factor, from a least squares tilt) such that the programme success and
     * loss to follow-up, weighted by cohort, equal the All table's headline
     * means (87.65 and 7.95). Each result is cached.
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

        $cohorts = self::cohorts($all);
        $cache = ['public' => [], 'private' => [], 'ppm' => []];

        foreach ($all as $i => $row) {
            $cells = [];

            foreach ($order as $position => $sector) {
                $cells[$position] = $sectors[$sector][$i];
            }

            $column = fn (string $key): array => array_map(fn (array $cell): float => (float) $cell[$key], $cells);
            $sus = self::alloc((int) $row['sus'], $column('sus'));
            $tested = self::allocCapped((int) $row['tested'], $column('tested'), $sus);
            $pos = self::allocCapped((int) $row['pos'], $column('pos'), $tested);
            $st = self::allocCapped((int) $row['st'], $column('st'), $pos);
            $mdr = self::allocCapped((int) $row['mdr'], $column('mdr'), $pos);
            $ai = self::allocCapped((int) $row['ai'], $column('ai'), $tested);

            $cohortTotal = self::cohortReadingAs($cohorts[$i], (float) $row['su'], (float) $row['ltfu']);
            $successTotal = self::round($cohortTotal * (float) $row['su'] / 100);
            $lostTotal = min(self::round($cohortTotal * (float) $row['ltfu'] / 100), $cohortTotal - $successTotal);
            $cohort = self::alloc($cohortTotal, $st);
            $success = self::allocCapped($successTotal, array_map(fn (int $c, array $cell): float => $c * (float) $cell['su'], $cohort, $cells), $cohort);
            $room = array_map(fn (int $c, int $s): int => $c - $s, $cohort, $success);
            $lost = self::allocCapped($lostTotal, array_map(fn (int $c, array $cell): float => $c * (float) $cell['ltfu'], $cohort, $cells), $room);

            // One shift per district so the tests weighted turnaround is the All row's.
            $tat = array_map(fn (array $cell): float => (float) $cell['tat'], $cells);

            for ($pass = 0; $pass < 4; $pass++) {
                $mass = (float) array_sum($tested);
                $mean = $mass > 0 ? array_sum(array_map(fn (float $t, int $n): float => $t * $n, $tat, $tested)) / $mass : (float) $row['tat'];
                $tat = array_map(fn (float $t): float => max(.5, $t + (float) $row['tat'] - $mean), $tat);
            }

            foreach ($order as $position => $sector) {
                $cell = $cells[$position];
                $cell['sus'] = $sus[$position];
                $cell['tested'] = $tested[$position];
                $cell['pos'] = $pos[$position];
                $cell['st'] = $st[$position];
                $cell['mdr'] = $mdr[$position];
                $cell['ai'] = $ai[$position];
                $cell['cohort'] = $cohort[$position];
                $cell['success'] = $success[$position];
                $cell['lost'] = $lost[$position];
                $cell['su'] = $cohort[$position] > 0 ? 100 * $success[$position] / $cohort[$position] : (float) $row['su'];
                $cell['ltfu'] = $cohort[$position] > 0 ? 100 * $lost[$position] / $cohort[$position] : (float) $row['ltfu'];
                $cell['tat'] = $tat[$position];
                // The All table's BMU count is the number of BMUs (kind), which
                // the public cell must hold; a public cell can hold more sites
                // (district hospitals) when its own table lists more.
                $cell['bmu_core'] = $sector === 'public' ? (int) $row['bmu'] : 0;
                $cell['bmu'] = $sector === 'public' ? max((int) $row['bmu'], (int) $cell['bmu']) : (int) $cell['bmu'];
                $cache[$sector][] = $cell;
            }
        }

        return $cache;
    }

    /**
     * The cohort size nearest to `$near` for which whole patients print as the
     * All table's rates (success to a whole percent, loss to one decimal).
     * Loss that the prototype draws above what success leaves is left as is.
     */
    private static function cohortReadingAs(int $near, float $su, float $ltfu): int
    {
        if ($ltfu > 100 - $su) {
            return $near;
        }

        $reads = function (int $c) use ($su, $ltfu): bool {
            $success = self::round($c * $su / 100);
            $lost = self::round($c * $ltfu / 100);

            return $success + $lost <= $c
                && round(100 * $success / $c) === round($su)
                && round(100 * $lost / $c, 1) === round($ltfu, 1);
        };

        for ($step = 0; $step <= max(12, (int) ($near * .12)); $step++) {
            foreach ([$near + $step, $near - $step] as $c) {
                if ($c >= 20 && $reads($c)) {
                    return $c;
                }
            }
        }

        return $near;
    }

    /**
     * Cohort size per district: the started count times a factor that makes
     * the cohort weighted success and loss rates equal the means of the All
     * table. The factors are the smallest change (least squares) that does it.
     *
     * @param  list<array<string, float|int|string>>  $all
     * @return list<int>
     */
    private static function cohorts(array $all): array
    {
        $n = count($all);
        $meanLtfu = array_sum(array_map(fn (array $r): float => (float) $r['ltfu'], $all)) / $n;
        $meanSu = array_sum(array_map(fn (array $r): float => (float) $r['su'], $all)) / $n;
        $a = [];
        $b = [];

        foreach ($all as $i => $r) {
            // The prototype draws success and loss independently, so in some
            // districts they add up to more than 100 percent. Loss is the one
            // that gives way (a cohort cannot lose more than it has left).
            $loss = min((float) $r['ltfu'], 100 - (float) $r['su']);
            $a[$i] = (int) $r['st'] * ($loss - $meanLtfu);
            $b[$i] = (int) $r['st'] * ((float) $r['su'] - $meanSu);
        }

        $aa = array_sum(array_map(fn (float $x): float => $x * $x, $a));
        $bb = array_sum(array_map(fn (float $x): float => $x * $x, $b));
        $ab = array_sum(array_map(fn (float $x, float $y): float => $x * $y, $a, $b));
        $c0 = -array_sum($a);
        $c1 = -array_sum($b);
        $det = $aa * $bb - $ab * $ab;
        $l1 = ($c0 * $bb - $c1 * $ab) / $det;
        $l2 = ($aa * $c1 - $ab * $c0) / $det;
        $cohorts = [];

        foreach ($all as $i => $r) {
            $cohorts[] = max(1, self::round((int) $r['st'] * max(.25, 1 + $l1 * $a[$i] + $l2 * $b[$i])));
        }

        return $cohorts;
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
        $cohortTotal = (int) ($d['cohort'] ?? array_sum($started));
        $successTotal = (int) ($d['success'] ?? self::round($cohortTotal * (float) $d['su'] / 100));
        $lostTotal = (int) ($d['lost'] ?? self::round($cohortTotal * (float) $d['ltfu'] / 100));

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
        foreach (['tat' => $tested] as $rate => $weights) {
            $sum = array_sum($weights);
            $norm[$rate] = $sum > 0 ? array_sum(array_map(fn (int $w, float $m): float => $w * $m, $weights, $multipliers[$rate])) / $sum : 1.0;
            $norm[$rate] = $norm[$rate] > 0 ? $norm[$rate] : 1.0;
        }

        $cohortB = self::alloc($cohortTotal, array_map(fn (int $x): float => $x + 1.0, $started));
        $successB = self::allocCapped(
            $successTotal,
            array_map(fn (int $c, float $m): float => $c * $m, $cohortB, $multipliers['su']),
            $cohortB,
        );
        $lostB = self::allocCapped(
            $lostTotal,
            array_map(fn (int $c, float $m): float => $c * $m, $cohortB, $multipliers['ltfu']),
            array_map(fn (int $c, int $x): int => $c - $x, $cohortB, $successB),
        );

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
                'cohort' => $cohortB[$j],
                'success' => $successB[$j],
                'lost' => $lostB[$j],
                'tat' => (float) $d['tat'] * $multipliers['tat'][$j] / $norm['tat'],
                'month_tested_weights' => $monthTested,
                'month_positive_weights' => $monthPositive,
            ];
        }

        return $out;
    }
}

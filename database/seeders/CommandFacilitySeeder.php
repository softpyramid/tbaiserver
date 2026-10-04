<?php

namespace Database\Seeders;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Models\City;
use App\Models\Facility;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use App\Support\DistrictMatch;
use Database\Seeders\Support\CommandPrototypeData as Proto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sindh facilities and their returns, sized so the programme totals match the
 * founder prototype (see CommandPrototypeData). Facility codes start with
 * "cc-" so the seeder can tell its own rows from captured ones. Captured
 * returns are counted first and taken off the district target, so adding
 * this seed to a database that already has returns does not double count.
 */
class CommandFacilitySeeder extends Seeder
{
    public const CODE_PREFIX = 'cc-';

    /** The reference day: months before it are complete, its own month stops here. */
    public const ANCHOR_YEAR = 2026;

    public const ANCHOR_MONTH = 10;

    public const ANCHOR_DAY = 3;

    /** Days of the month that carry a return in a complete month. */
    private const MONTH_DAYS = [4, 11, 18, 25];

    /** Positivity pushes up over the last four months in Karachi Central. */
    private const KARACHI_CENTRAL_PUSH = [1, 1, 1, 1, 1, 1, .88, .96, 1.05, 1.14];

    private int $submitterId = 0;

    public function run(): void
    {
        if (! app()->environment('local', 'dev', 'staging')) {
            return;
        }

        $submitter = User::query()->where('email', 'admin@tbai.test')->first()
            ?? User::query()->orderBy('id')->first();

        if ($submitter === null) {
            throw new RuntimeException('Run LoginSeeder before CommandFacilitySeeder. There is no user to own the returns.');
        }

        $this->submitterId = $submitter->id;
        $sindh = Province::query()->where('slug', 'sindh')->firstOrFail();
        $cities = City::query()->where('province_id', $sindh->id)->get()
            ->mapWithKeys(fn (City $city): array => [DistrictMatch::key($city->name) => $city]);
        $captured = $this->capturedTotals();
        $targets = Proto::targets();

        foreach (['public', 'private', 'ppm'] as $sector) {
            foreach ($targets[$sector] as $i => $target) {
                $city = $cities->get(DistrictMatch::key((string) $target['name']));

                if ($city === null) {
                    continue;
                }

                $existing = $captured[$city->id][$sector] ?? ['count' => 0, 'sus' => 0, 'tested' => 0, 'pos' => 0, 'st' => 0, 'empty' => [], 'bmu_kind' => 0];
                // Sites that already report take their slot and their numbers off the target.
                // Sites that exist but have no returns take a slot and get a series.
                $slots = (int) $target['bmu'] - $existing['count'];

                if ($slots <= 0) {
                    continue;
                }

                $reduced = $target;
                foreach (['sus', 'tested', 'pos', 'st'] as $metric) {
                    $reduced[$metric] = max(0, (int) $target[$metric] - $existing[$metric]);
                }
                $reduced['st'] = min((int) $reduced['st'], (int) $reduced['pos']);

                if ((int) $target['st'] > 0 && $reduced['st'] < (int) $target['st']) {
                    $ratio = $reduced['st'] / (int) $target['st'];
                    $reduced['cohort'] = Proto::round((int) $target['cohort'] * $ratio);
                    $reduced['success'] = Proto::round((int) $target['success'] * $ratio);
                    $reduced['lost'] = min(Proto::round((int) $target['lost'] * $ratio), $reduced['cohort'] - $reduced['success']);
                }

                $empties = $existing['empty'];
                $bmus = Proto::bmus($sector, $reduced, $slots);
                $key = DistrictMatch::key($city->name);
                $needBmu = max(0, (int) $target['bmu_core'] - $existing['bmu_kind']);

                foreach ($bmus as $j => $bmu) {
                    if ($j < count($empties)) {
                        $facility = $empties[$j];
                        $ordinal = $j + 1;
                    } else {
                        $ordinal = $j - count($empties) + 1;
                        $kind = $this->kind($sector, $ordinal, $needBmu);
                        $facility = $this->facility($city, $sector, $kind, $ordinal, $i, $needBmu);
                    }

                    $this->seedSeries($facility, $bmu, $sector, $key, $ordinal);
                }
            }
        }

    }

    /** The first sites of a public cell are BMUs, as many as the All table counts; any further site is a district hospital. */
    private function kind(string $sector, int $ordinal, int $needBmu): FacilityKind
    {
        return match ($sector) {
            'public' => $ordinal <= $needBmu ? FacilityKind::Bmu : FacilityKind::Hospital,
            'private' => FacilityKind::Clinic,
            default => $ordinal % 2 === 0 ? FacilityKind::Hospital : FacilityKind::Clinic,
        };
    }

    /**
     * Captured (non-seeded) sites per city and sector. Sites with returns are
     * counted with their numbers so they can be taken off the target; sites
     * with no return are listed so they receive a series.
     *
     * @return array<int, array<string, array{count: int, sus: int, tested: int, pos: int, st: int, empty: list<Facility>, bmu_kind: int}>>
     */
    private function capturedTotals(): array
    {
        $totals = [];
        $facilities = Facility::query()
            ->where(fn ($query) => $query->whereNull('code')->orWhere('code', 'not like', self::CODE_PREFIX.'%'))
            ->orderBy('id')
            ->get();

        foreach ($facilities as $facility) {
            $cell = $totals[$facility->city_id][$facility->sector->value] ?? ['count' => 0, 'sus' => 0, 'tested' => 0, 'pos' => 0, 'st' => 0, 'empty' => [], 'bmu_kind' => 0];
            $sums = DB::table('daily_returns')->where('facility_id', $facility->id)
                ->selectRaw('COUNT(*) as n, COALESCE(SUM(suspects),0) as s, COALESCE(SUM(tested),0) as t, COALESCE(SUM(positive),0) as p, COALESCE(SUM(started_treatment),0) as a')
                ->first();

            if ($facility->kind === FacilityKind::Bmu) {
                $cell['bmu_kind']++;
            }

            if ((int) ($sums->n ?? 0) === 0) {
                $cell['empty'][] = $facility;
            } else {
                $cell['count']++;
                $cell['sus'] += (int) ($sums->s ?? 0);
                $cell['tested'] += (int) ($sums->t ?? 0);
                $cell['pos'] += (int) ($sums->p ?? 0);
                $cell['st'] += (int) ($sums->a ?? 0);
            }

            $totals[$facility->city_id][$facility->sector->value] = $cell;
        }

        return $totals;
    }

    private function facility(City $city, string $sector, FacilityKind $kind, int $ordinal, int $index, int $bmus = 0): Facility
    {
        $code = self::CODE_PREFIX.$sector.'-'.Str::slug($city->name).'-'.$ordinal;
        $name = match ($sector) {
            'public' => $kind === FacilityKind::Hospital
                ? "{$city->name} District Hospital".($ordinal - $bmus > 1 ? ' '.($ordinal - $bmus) : '')
                : "{$city->name} BMU {$ordinal}",
            'private' => "{$city->name} Private Clinic {$ordinal}",
            default => "{$city->name} PPM Site {$ordinal}",
        };

        $team = Team::query()->firstOrCreate(
            ['slug' => $code],
            ['name' => $name, 'is_personal' => false],
        );

        return Facility::query()->firstOrCreate(
            ['team_id' => $team->id],
            [
                'city_id' => $city->id,
                'sector' => Sector::from($sector),
                'kind' => $kind,
                'code' => $code,
                'status' => FacilityStatus::Active,
                'latitude' => round((float) $city->latitude + (Proto::rnd($index * 3 + $ordinal, 11) - .5) * .3, 5),
                'longitude' => round((float) $city->longitude + (Proto::rnd($index * 5 + $ordinal, 12) - .5) * .3, 5),
            ],
        );
    }

    /**
     * Writes one facility's returns and monthly tables.
     *
     * @param  array<string, mixed>  $bmu
     */
    private function seedSeries(Facility $facility, array $bmu, string $sector, string $districtKey, int $ordinal): void
    {
        $weightsTested = $bmu['month_tested_weights'];
        $weightsPositive = $bmu['month_positive_weights'];

        if ($districtKey === 'karachi-central') {
            foreach ($weightsPositive as $m => $weight) {
                $weightsPositive[$m] = $weight * self::KARACHI_CENTRAL_PUSH[$m];
            }
        }

        $tested = Proto::alloc((int) $bmu['tested'], $weightsTested);
        $positive = Proto::alloc((int) $bmu['pos'], $weightsPositive);
        $suspects = Proto::alloc((int) $bmu['sus'], $weightsTested);

        foreach ($tested as $m => $value) {
            $positive[$m] = min($positive[$m], $value);
        }

        $started = Proto::alloc((int) $bmu['st'], array_values($positive));

        foreach ($started as $m => $value) {
            $started[$m] = min($value, $positive[$m]);
        }

        $tat = $this->tatSeries((float) $bmu['tat'], $tested, $sector, $districtKey, $ordinal);
        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($tested as $m => $monthTested) {
            $days = $m < 9 ? self::MONTH_DAYS : range(1, self::ANCHOR_DAY);
            $slots = array_fill(0, count($days), 1);
            $t = Proto::alloc($monthTested, $slots);
            $p = Proto::alloc($positive[$m], $slots);
            $s = Proto::alloc($suspects[$m], $slots);
            $a = Proto::alloc($started[$m], $slots);

            foreach ($days as $d => $day) {
                $rows[] = [
                    'facility_id' => $facility->id,
                    'reported_on' => sprintf('%04d-%02d-%02d', self::ANCHOR_YEAR, $m + 1, $day),
                    'submitted_by' => $this->submitterId,
                    'suspects' => $s[$d],
                    'tested' => $t[$d],
                    'positive' => min($p[$d], $t[$d]),
                    'negative' => $t[$d] - min($p[$d], $t[$d]),
                    'started_treatment' => min($a[$d], $p[$d]),
                    'follow_up' => min($a[$d], $p[$d]),
                    'median_tat_days' => $tat[$m],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows, $facility, $bmu, $tested, $positive, $started, $now): void {
            foreach (array_chunk($rows, 400) as $chunk) {
                DB::table('daily_returns')->insertOrIgnore($chunk);
            }

            $mdr = Proto::alloc((int) $bmu['mdr'], Proto::MONTH_WEIGHTS);
            $ai = Proto::alloc((int) $bmu['ai'], $tested);
            $outcomes = [];
            $mdrRows = [];
            $aiRows = [];
            $contactRows = [];
            $hivRows = [];

            $cohortTotal = (int) $bmu['cohort'];
            $successTotal = (int) $bmu['success'];
            $lostTotal = (int) $bmu['lost'];
            $cohortByMonth = Proto::alloc($cohortTotal, array_map(fn (int $x): int => $x + 1, $started));
            $successByMonth = Proto::allocCapped($successTotal, $cohortByMonth, $cohortByMonth);
            $lostByMonth = Proto::allocCapped($lostTotal, $cohortByMonth, array_map(fn (int $c, int $x): int => $c - $x, $cohortByMonth, $successByMonth));

            foreach ($cohortByMonth as $m => $cohort) {
                $month = sprintf('%04d-%02d', self::ANCHOR_YEAR, $m + 1);
                $successful = min($successByMonth[$m], $cohort);
                $lost = min($lostByMonth[$m], $cohort - $successful);
                $rest = max(0, $cohort - $successful - $lost);
                $died = Proto::round($rest * .4);
                $stamp = ['facility_id' => $facility->id, 'month' => $month, 'created_at' => $now, 'updated_at' => $now];

                $outcomes[] = $stamp + [
                    'cohort_size' => $cohort,
                    'successful' => $successful,
                    'lost_to_follow_up' => $lost,
                    'died' => $died,
                    'failed' => $rest - $died,
                ];
                $mdrRows[] = $stamp + ['cases' => $mdr[$m]];
                $aiRows[] = $stamp + ['tests' => $ai[$m]];

                $listed = Proto::round($positive[$m] * 6.5);
                $contactRows[] = $stamp + [
                    'contacts_listed' => $listed,
                    'contacts_evaluated' => Proto::round($listed * (.55 + .35 * Proto::rnd($facility->id, 12))),
                ];
                $hivRows[] = $stamp + [
                    'tb_patients' => $positive[$m],
                    'hiv_status_recorded' => Proto::round($positive[$m] * (.6 + .35 * Proto::rnd($facility->id, 13))),
                ];
            }

            DB::table('treatment_outcomes')->upsert($outcomes, ['facility_id', 'month'], ['cohort_size', 'successful', 'lost_to_follow_up', 'died', 'failed', 'updated_at']);
            DB::table('mdr_rr_cases')->upsert($mdrRows, ['facility_id', 'month'], ['cases', 'updated_at']);
            DB::table('ai_test_counts')->upsert($aiRows, ['facility_id', 'month'], ['tests', 'updated_at']);
            DB::table('contact_tracing')->upsert($contactRows, ['facility_id', 'month'], ['contacts_listed', 'contacts_evaluated', 'updated_at']);
            DB::table('hiv_testing')->upsert($hivRows, ['facility_id', 'month'], ['tb_patients', 'hiv_status_recorded', 'updated_at']);
        });
    }

    /**
     * Reported turnaround per month. The facility mean stays at its prototype
     * value, except where a scenario shapes the last two months so the
     * Intelligence Engine alerts have something true to report.
     *
     * @param  list<int>  $tested
     * @return list<float>
     */
    private function tatSeries(float $base, array $tested, string $sector, string $districtKey, int $ordinal): array
    {
        $shape = null;

        if ($districtKey === 'kambar-shahdadkot') {
            $shape = [3.2, 1.4];
        } elseif ($districtKey === 'umerkot' && $sector === 'public' && $ordinal === 1) {
            $shape = [1.8, 4.2];
        }

        if ($shape === null) {
            return array_fill(0, 10, round($base, 2));
        }

        $total = array_sum($tested);
        $early = array_sum(array_slice($tested, 0, 8));
        $baseline = $early > 0
            ? max(.2, ($base * $total - $shape[0] * $tested[8] - $shape[1] * $tested[9]) / $early)
            : $base;
        $series = array_fill(0, 8, round($baseline, 2));
        $series[] = $shape[0];
        $series[] = $shape[1];

        return $series;
    }
}

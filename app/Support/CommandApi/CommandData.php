<?php

namespace App\Support\CommandApi;

use App\Models\City;
use App\Models\Facility;
use App\Models\User;
use App\Support\DataScope;
use App\Support\DistrictMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * All the SQL behind the command center API. Everything goes through
 * DataScope first, so a role never aggregates a row it may not see.
 */
final class CommandData
{
    public function __construct(private DataScope $scope) {}

    /**
     * @return Builder<Facility>
     */
    public function facilities(Selection $s, User $user): Builder
    {
        $query = Facility::query();
        $this->scope->constrainFacilities($query, $user);

        if ($s->cityId !== null) {
            $query->where('facilities.city_id', $s->cityId);
        } elseif ($s->provinceId !== null) {
            $query->whereIn('facilities.city_id', City::query()->where('province_id', $s->provinceId)->select('id'));
        }

        if ($s->sector !== null) {
            $query->where('facilities.sector', $s->sector->value);
        }

        return $query;
    }

    /**
     * Districts with at least one active facility in the selection, in scope.
     *
     * @return list<City>
     */
    public function cities(Selection $s, User $user): array
    {
        $ids = $this->facilities($s, $user)->where('facilities.status', 'active')->select('facilities.city_id');

        return array_values(City::query()->whereIn('cities.id', $ids)->orderBy('cities.name')->get()->all());
    }

    /**
     * Summed daily returns, grouped by any of city_id, facility_id, month.
     *
     * @param  list<string>  $by
     * @return list<array<string, mixed>>
     */
    public function returns(Selection $s, User $user, array $by = []): array
    {
        $query = DB::table('daily_returns as r')
            ->join('facilities as f', 'f.id', '=', 'r.facility_id')
            ->whereIn('r.facility_id', $this->facilities($s, $user)->select('facilities.id'))
            ->where('r.reported_on', '>=', $s->from)
            ->where('r.reported_on', '<=', $s->to);

        foreach ($by as $key) {
            [$select, $group] = $this->returnGroup($key);
            $query->addSelect(new Expression($select))->groupBy(new Expression($group));
        }

        $query->addSelect(new Expression(
            'COALESCE(SUM(r.suspects),0) as suspects, COALESCE(SUM(r.tested),0) as tested, COALESCE(SUM(r.positive),0) as positive,'
            .' COALESCE(SUM(r.negative),0) as negative, COALESCE(SUM(r.started_treatment),0) as started,'
            .' COALESCE(SUM(CASE WHEN r.median_tat_days IS NOT NULL AND r.tested > 0 THEN r.median_tat_days * r.tested ELSE 0 END),0) as tat_weighted,'
            .' COALESCE(SUM(CASE WHEN r.median_tat_days IS NOT NULL AND r.tested > 0 THEN r.tested ELSE 0 END),0) as tat_weight,'
            ." COUNT(DISTINCT CASE WHEN f.status = 'active' THEN r.facility_id END) as active,"
            ." COUNT(DISTINCT CASE WHEN f.status = 'active' AND f.kind = 'bmu' THEN r.facility_id END) as active_bmus",
        ));

        return $this->rows($query);
    }

    /**
     * Summed monthly table values. Months count when they overlap the period.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $by  city_id, facility_id, month
     * @return list<array<string, mixed>>
     */
    public function monthly(string $table, array $columns, Selection $s, User $user, array $by = []): array
    {
        $query = DB::table($table.' as t')
            ->join('facilities as f', 'f.id', '=', 't.facility_id')
            ->whereIn('t.facility_id', $this->facilities($s, $user)->select('facilities.id'))
            ->where('t.month', '>=', substr($s->from, 0, 7))
            ->where('t.month', '<=', substr($s->to, 0, 7));

        foreach ($by as $key) {
            [$select, $group] = match ($key) {
                'facility_id' => ['t.facility_id as facility_id', 't.facility_id'],
                'month' => ['t.month as month', 't.month'],
                default => ['f.city_id as city_id', 'f.city_id'],
            };
            $query->addSelect(new Expression($select))->groupBy(new Expression($group));
        }

        foreach ($columns as $column) {
            $query->addSelect(new Expression($this->sum($column)));
        }

        $query->addSelect(new Expression('COUNT(*) as record_count'));

        return $this->rows($query);
    }

    /**
     * Index rows by one or more of their columns, joined with a bar.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<int|string, array<string, mixed>>
     */
    public function keyed(array $rows, string ...$keys): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[implode('|', array_map(fn (string $key): string => (string) $row[$key], $keys))] = $row;
        }

        return $out;
    }

    /**
     * Derived figures from summed counts. Rates are percentages with two decimals.
     *
     * @param  array<string, mixed>  $sum  returns row
     * @param  array<string, mixed>|null  $outcome  treatment_outcomes row
     * @param  array<string, mixed>|null  $mdr
     * @param  array<string, mixed>|null  $ai
     * @return array<string, mixed>
     */
    public function metrics(array $sum, ?array $outcome, ?array $mdr, ?array $ai): array
    {
        $suspects = (int) $sum['suspects'];
        $tested = (int) $sum['tested'];
        $positive = (int) $sum['positive'];
        $started = (int) $sum['started'];
        $cohort = $outcome === null ? 0 : (int) $outcome['cohort_size'];
        $aiOn = (bool) config('tbai.features.ai_tests');

        return [
            'suspects' => $suspects,
            'tested' => $tested,
            'positive' => $positive,
            'negative' => (int) $sum['negative'],
            'started' => $started,
            'positivity' => self::rate($positive, $tested),
            'started_pct' => self::rate($started, $positive),
            'success_pct' => $cohort > 0 ? self::rate((int) $outcome['successful'], $cohort) : null,
            'ltfu_pct' => $cohort > 0 ? self::rate((int) $outcome['lost_to_follow_up'], $cohort) : null,
            'tat_days' => (float) $sum['tat_weight'] > 0 ? round((float) $sum['tat_weighted'] / (float) $sum['tat_weight'], 2) : null,
            'mdr_rr' => $mdr !== null && (int) $mdr['record_count'] > 0 ? (int) $mdr['cases'] : null,
            'ai_tests' => $aiOn && $ai !== null && (int) $ai['record_count'] > 0 ? (int) $ai['tests'] : null,
            'bmus' => (int) $sum['active'],
            'active_bmus' => (int) $sum['active_bmus'],
            'treatment_gap' => $positive - $started,
            'coverage_pct' => self::rate($tested, $suspects),
        ];
    }

    /**
     * One row per in-scope district with every figure the map and tables use.
     *
     * @return list<array<string, mixed>>
     */
    public function districts(Selection $s, User $user): array
    {
        $returns = $this->keyed($this->returns($s, $user, ['city_id']), 'city_id');
        $outcomes = $this->keyed($this->monthly('treatment_outcomes', ['cohort_size', 'successful', 'lost_to_follow_up'], $s, $user, ['city_id']), 'city_id');
        $mdr = $this->keyed($this->monthly('mdr_rr_cases', ['cases'], $s, $user, ['city_id']), 'city_id');
        $ai = $this->keyed($this->monthly('ai_test_counts', ['tests'], $s, $user, ['city_id']), 'city_id');
        $empty = ['suspects' => 0, 'tested' => 0, 'positive' => 0, 'negative' => 0, 'started' => 0, 'tat_weighted' => 0, 'tat_weight' => 0, 'active' => 0, 'active_bmus' => 0];
        $rows = [];

        foreach ($this->cities($s, $user) as $city) {
            $key = (string) $city->id;
            $metrics = $this->metrics($returns[$key] ?? $empty, $outcomes[$key] ?? null, $mdr[$key] ?? null, $ai[$key] ?? null);
            $seat = DistrictSites::seat($city->name, (float) $city->latitude, (float) $city->longitude);

            $rows[] = [
                'city_id' => $city->id,
                'match_key' => DistrictMatch::key($city->name),
                'name' => $city->name,
                'short_name' => DistrictSites::shortName($city->name),
                'seat' => $seat,
            ] + $metrics;
        }

        return $rows;
    }

    /**
     * The scope total for a set of district rows, rates from summed counts.
     *
     * @return array<string, mixed>
     */
    public function totals(Selection $s, User $user): array
    {
        $sum = $this->returns($s, $user)[0];
        $outcome = $this->monthly('treatment_outcomes', ['cohort_size', 'successful', 'lost_to_follow_up'], $s, $user)[0];
        $mdr = $this->monthly('mdr_rr_cases', ['cases'], $s, $user)[0];
        $ai = $this->monthly('ai_test_counts', ['tests'], $s, $user)[0];

        $total = $this->metrics($sum, $outcome, $mdr, $ai);

        // Medians cannot be pooled. The programme figure is the median of the
        // district medians (the prototype's headline works this way); a single
        // district keeps its own value.
        $tats = array_values(array_filter(
            array_column($this->districts($s, $user), 'tat_days'),
            fn (mixed $v): bool => $v !== null,
        ));

        if (count($tats) >= 2) {
            sort($tats);
            $mid = intdiv(count($tats), 2);
            $total['tat_days'] = round(count($tats) % 2 === 1 ? (float) $tats[$mid] : ((float) $tats[$mid - 1] + (float) $tats[$mid]) / 2, 2);
        }

        return $total;
    }

    /**
     * A figure rounded for display, as an integer when it has no decimals.
     */
    public static function number(mixed $value, int $decimals): int|float|null
    {
        if ($value === null) {
            return null;
        }

        return $decimals === 0 ? (int) round((float) $value) : round((float) $value, $decimals);
    }

    public static function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : null;
    }

    /**
     * Select and group expressions for one return grouping. Literal SQL only.
     *
     * @return array{0: literal-string, 1: literal-string}
     */
    private function returnGroup(string $key): array
    {
        return match ($key) {
            'facility_id' => ['r.facility_id as facility_id', 'r.facility_id'],
            'month' => match (DB::connection()->getDriverName()) {
                'sqlite' => ["strftime('%Y-%m', r.reported_on) as month", "strftime('%Y-%m', r.reported_on)"],
                'pgsql' => ["to_char(r.reported_on, 'YYYY-MM') as month", "to_char(r.reported_on, 'YYYY-MM')"],
                default => ["DATE_FORMAT(r.reported_on, '%Y-%m') as month", "DATE_FORMAT(r.reported_on, '%Y-%m')"],
            },
            default => ['f.city_id as city_id', 'f.city_id'],
        };
    }

    /**
     * @return literal-string
     */
    private function sum(string $column): string
    {
        return match ($column) {
            'cohort_size' => 'COALESCE(SUM(t.cohort_size),0) as cohort_size',
            'successful' => 'COALESCE(SUM(t.successful),0) as successful',
            'lost_to_follow_up' => 'COALESCE(SUM(t.lost_to_follow_up),0) as lost_to_follow_up',
            'cases' => 'COALESCE(SUM(t.cases),0) as cases',
            'tests' => 'COALESCE(SUM(t.tests),0) as tests',
            'contacts_listed' => 'COALESCE(SUM(t.contacts_listed),0) as contacts_listed',
            'tb_patients' => 'COALESCE(SUM(t.tb_patients),0) as tb_patients',
            default => throw new \InvalidArgumentException("Unknown column {$column}."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(QueryBuilder $query): array
    {
        return array_values($query->get()->map(fn (object $row): array => (array) $row)->all());
    }
}

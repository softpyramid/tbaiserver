<?php

namespace App\Support\CommandApi;

use App\Enums\AlertComparator;
use App\Models\AlertRule;
use App\Models\City;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Evaluates the four alert rules from live returns. "This month" is the
 * calendar month of the period end; the month before it is the comparison.
 * A positivity run counts only when each step rises by at least half a
 * percentage point, so rounding noise never starts a streak. An
 * improvement needs the turnaround to fall by at least a tenth.
 */
final class AlertEngine
{
    public const MIN_STREAK_STEP = 0.5;

    public const MIN_IMPROVEMENT = 0.9;

    public function __construct(private CommandData $data) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function evaluate(Selection $s, User $user): array
    {
        $to = new \DateTimeImmutable($s->to);
        $from = $to->modify('first day of this month')->modify('-4 months')->format('Y-m-d');
        $window = $s->withPeriod($from, $s->to);
        $months = $window->months();
        $rules = AlertRule::query()->get()->keyBy('severity');
        $map = fn (?int $cityId): array => ['sector' => $s->sector->value ?? 'all', 'indicator' => '', 'selected' => $cityId];
        $alerts = [];

        $alerts = [...$alerts, ...$this->critical($s, $user, $rules, $map)];
        $alerts = [...$alerts, ...$this->attention($window, $user, $months, $rules, $map)];
        $districts = $this->districtMonths($window, $user);
        $alerts = [...$alerts, ...$this->emerging($districts, $months, $rules, $map)];
        $alerts = [...$alerts, ...$this->improvement($districts, $months, $rules, $map)];

        return $alerts;
    }

    /**
     * One line per severity, strongest first.
     *
     * @param  list<array<string, mixed>>  $alerts
     * @return list<array<string, mixed>>
     */
    public function lines(array $alerts): array
    {
        $lines = [];

        foreach (['critical', 'attention', 'emerging', 'improvement'] as $severity) {
            foreach ($alerts as $alert) {
                if ($alert['severity'] === $severity) {
                    $lines[] = $alert;

                    break;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  Collection<string, AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    private function critical(Selection $s, User $user, Collection $rules, callable $map): array
    {
        [$threshold, $comparator] = $this->rule($rules, 'critical', 0.0, AlertComparator::Gt);
        $count = 0;

        foreach ($this->data->districts($s, $user) as $d) {
            if ($comparator->matches((float) $d['treatment_gap'], $threshold)) {
                $count += (int) $d['treatment_gap'];
            }
        }

        if ($count <= 0) {
            return [];
        }

        return [[
            'severity' => 'critical',
            'text' => number_format($count).' diagnosed patients have no treatment-start record',
            'metric' => 'treatment_gap',
            'values' => ['count' => $count],
            'subject' => ['type' => 'scope', 'id' => null, 'name' => $s->scopeName],
            'map' => ['indicator' => 'treatment_gap'] + $map(null),
        ]];
    }

    /**
     * @param  list<string>  $months
     * @param  Collection<string, AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    private function attention(Selection $window, User $user, array $months, Collection $rules, callable $map): array
    {
        if (count($months) < 2) {
            return [];
        }

        $current = $months[count($months) - 1];
        $previous = $months[count($months) - 2];
        [$threshold, $comparator] = $this->rule($rules, 'attention', 2.3, AlertComparator::Gte);
        $by = $this->data->keyed($this->data->returns($window, $user, ['facility_id', 'month']), 'facility_id', 'month');
        $facilities = Facility::query()->with(['team', 'city'])->whereIn('id', array_unique(array_map(fn (array $r): int => (int) $r['facility_id'], $by)))->get()->keyBy('id');
        $alerts = [];

        foreach ($facilities as $id => $facility) {
            $now = $by[$id.'|'.$current] ?? null;
            $before = $by[$id.'|'.$previous] ?? null;
            $nowTat = $this->tat($now);
            $beforeTat = $this->tat($before);

            if ($nowTat === null || $beforeTat === null || $beforeTat <= 0) {
                continue;
            }

            $ratio = round($nowTat / $beforeTat, 2);

            if (! $comparator->matches($ratio, $threshold)) {
                continue;
            }

            $label = DistrictSites::facilityLabel($facility->code, $facility->team->name);
            $alerts[] = [
                'sort' => $ratio,
                'severity' => 'attention',
                'text' => sprintf('%s diagnostic TAT increased %s× this month (%s)', $label, rtrim(rtrim(number_format($ratio, 1), '0'), '.'), $facility->city->name),
                'metric' => 'tat_ratio',
                'values' => ['ratio' => round($ratio, 1), 'facility' => $label, 'current_days' => round($nowTat, 1), 'previous_days' => round($beforeTat, 1)],
                'subject' => ['type' => 'facility', 'id' => $facility->id, 'name' => $label, 'district' => ['city_id' => $facility->city_id, 'name' => $facility->city->name]],
                'map' => ['indicator' => 'median_tat'] + $map($facility->city_id),
            ];
        }

        return $this->ranked($alerts);
    }

    /**
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function districtMonths(Selection $window, User $user): array
    {
        $out = [];

        foreach ($this->data->returns($window, $user, ['city_id', 'month']) as $row) {
            $out[(int) $row['city_id']][$row['month']] = $row;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, array<string, mixed>>>  $districts
     * @param  list<string>  $months
     * @param  Collection<string, AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    private function emerging(array $districts, array $months, Collection $rules, callable $map): array
    {
        [$threshold, $comparator] = $this->rule($rules, 'emerging', 3.0, AlertComparator::Gte);
        $names = City::query()->whereIn('id', array_keys($districts))->pluck('name', 'id');
        $alerts = [];

        foreach ($districts as $cityId => $perMonth) {
            $streak = 0;

            for ($i = count($months) - 1; $i >= 1; $i--) {
                $now = $this->positivity($perMonth[$months[$i]] ?? null);
                $before = $this->positivity($perMonth[$months[$i - 1]] ?? null);

                if ($now === null || $before === null || $now - $before < self::MIN_STREAK_STEP) {
                    break;
                }

                $streak++;
            }

            if ($streak === 0 || ! $comparator->matches((float) $streak, $threshold)) {
                continue;
            }

            $name = (string) $names->get($cityId);
            $alerts[] = [
                'sort' => $streak + ($this->positivity($perMonth[$months[count($months) - 1]] ?? null) ?? 0) / 1000,
                'severity' => 'emerging',
                'text' => "{$name} positivity rate increased for {$streak} consecutive reporting periods",
                'metric' => 'positivity_streak',
                'values' => ['periods' => $streak],
                'subject' => ['type' => 'district', 'id' => $cityId, 'name' => $name],
                'map' => ['indicator' => 'positivity'] + $map($cityId),
            ];
        }

        return $this->ranked($alerts);
    }

    /**
     * @param  array<int, array<string, array<string, mixed>>>  $districts
     * @param  list<string>  $months
     * @param  Collection<string, AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    private function improvement(array $districts, array $months, Collection $rules, callable $map): array
    {
        if (count($months) < 2) {
            return [];
        }

        $current = $months[count($months) - 1];
        $previous = $months[count($months) - 2];
        [$threshold, $comparator] = $this->rule($rules, 'improvement', 1.0, AlertComparator::Lt);
        $names = City::query()->whereIn('id', array_keys($districts))->pluck('name', 'id');
        $alerts = [];

        foreach ($districts as $cityId => $perMonth) {
            $now = $this->tat($perMonth[$current] ?? null);
            $before = $this->tat($perMonth[$previous] ?? null);

            if ($now === null || $before === null || $before <= 0) {
                continue;
            }

            $ratio = round($now / $before, 2);

            if (! $comparator->matches($ratio, $threshold) || $ratio > self::MIN_IMPROVEMENT) {
                continue;
            }

            $name = (string) $names->get($cityId);
            $alerts[] = [
                'sort' => 1 - $ratio,
                'severity' => 'improvement',
                'text' => sprintf('%s reduced median diagnostic TAT from %s → %s days', $name, number_format($before, 1), number_format($now, 1)),
                'metric' => 'tat_ratio',
                'values' => ['from_days' => round($before, 1), 'to_days' => round($now, 1)],
                'subject' => ['type' => 'district', 'id' => $cityId, 'name' => $name],
                'map' => ['indicator' => 'median_tat'] + $map($cityId),
            ];
        }

        return $this->ranked($alerts);
    }

    /**
     * The stored rule for a severity, or the default when the rule was removed.
     *
     * @param  Collection<string, AlertRule>  $rules
     * @return array{0: float, 1: AlertComparator}
     */
    private function rule(Collection $rules, string $severity, float $threshold, AlertComparator $comparator): array
    {
        $rule = $rules->get($severity);

        return $rule instanceof AlertRule ? [(float) $rule->threshold, $rule->comparator] : [$threshold, $comparator];
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function tat(?array $row): ?float
    {
        if ($row === null || (float) $row['tat_weight'] <= 0) {
            return null;
        }

        return (float) $row['tat_weighted'] / (float) $row['tat_weight'];
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function positivity(?array $row): ?float
    {
        if ($row === null || (int) $row['tested'] <= 0) {
            return null;
        }

        return (int) $row['positive'] / (int) $row['tested'] * 100;
    }

    /**
     * Strongest first, without the sort helper.
     *
     * @param  list<array<string, mixed>>  $alerts
     * @return list<array<string, mixed>>
     */
    private function ranked(array $alerts): array
    {
        usort($alerts, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_map(function (array $alert): array {
            unset($alert['sort']);

            return $alert;
        }, $alerts);
    }
}

<?php

namespace App\Support\CommandApi;

use App\Models\User;
use App\Support\IncidenceEstimateLookup;
use App\Support\NotificationRate;

final class CumulativeBuilder
{
    public function __construct(private CommandData $data) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Selection $s, User $user): array
    {
        $t = $this->data->totals($s, $user);
        $previous = $this->data->totals($s->previous(), $user);
        $positivityChange = PositivityChange::label($t['positive'], $t['tested'], $previous['positive'], $previous['tested']);
        $ai = (bool) config('tbai.features.ai_tests');

        return [
            'cascade' => [
                $this->item('suspects', 'Suspects identified', $t['suspects']),
                $this->item('tested', 'Tested', $t['tested']),
                $this->item('positive', 'TB positive', $t['positive']),
                $this->item('negative', 'TB negative', $t['negative']),
                $this->item('started_treatment', 'Started treatment', $t['started']),
                $this->item('ai_assisted_tests', 'AI-assisted tests', $ai ? $t['ai_tests'] : null, 'not_collected'),
            ],
            'performance' => [
                'rings' => [
                    $this->ring('positivity', 'Positivity rate', $t['positivity'], 30, 'not_enough_returns', $positivityChange),
                    $this->ring('treatment_success', 'Treatment success', $t['success_pct'], 100, 'not_collected'),
                    $this->ring('lost_to_follow_up', 'Lost to follow-up', $t['ltfu_pct'], 30, 'not_collected'),
                ],
                'tiles' => [
                    $this->tile('median_tat', 'Median TAT', $t['tat_days'], 'days', 'not_enough_returns', 'provisional'),
                    $this->tile('active_bmus', 'Active BMUs', $t['active_bmus'], null),
                    $this->tile('negative', 'TB negative', $t['negative'], null),
                ],
            ],
            'months' => $this->months($s, $user),
            'table' => [
                $this->row('suspects', '👥', 'Suspects identified', 'How many people entered the diagnostic pathway', $t['suspects'], null),
                $this->row('tested', '🔬', 'Tested', 'How many actually received testing', $t['tested'], null),
                $this->row('positive', '🟢', 'TB positive', 'Confirmed/diagnosed cases', $t['positive'], null),
                $this->row('negative', '⚪', 'TB negative', 'Ruled out through diagnostic pathway', $t['negative'], null),
                $this->row('positivity', '📊', 'Positivity rate', 'Positive results ÷ valid completed tests x 100', $t['positivity'], '%', 'not_enough_returns', null, $positivityChange),
                $this->row('started_treatment', '💊', 'Started treatment', 'Diagnosed → treatment', $t['started'], null),
                $this->row('treatment_success', '✅', 'Treatment success', 'Programme performance', $t['success_pct'], '%', 'not_collected'),
                $this->row('lost_to_follow_up', '🔴', 'Lost to follow-up', 'Where patients are dropping out', $t['ltfu_pct'], '%', 'not_collected'),
                $this->row('median_tat', '⏱', 'Median TAT', 'Diagnostic efficiency', $t['tat_days'], 'days', 'not_enough_returns', 'provisional'),
                $this->row('active_bmus', '🏥', 'Active BMUs', 'Operational coverage', $t['active_bmus'], null),
                $this->row('ai_assisted_tests', '🤖', 'AI-assisted tests', 'TB-AI diagnostic utilization', $ai ? $t['ai_tests'] : null, null, 'not_collected'),
            ],
            'has_returns' => $t['bmus'] > 0,
            'notification' => $this->notification($s, $user),
            'estimate' => IncidenceEstimateLookup::card(
                $s->provinceId,
                $s->cityId,
                (int) substr($s->to, 0, 4),
            ),
        ];
    }

    /**
     * Same place and population rules as the /command card.
     *
     * @return array<string, mixed>
     */
    private function notification(Selection $s, User $user): array
    {
        return NotificationRate::forSelection($s->provinceId, $s->cityId, $s->to, $user);
    }

    /**
     * Running totals, one entry per calendar month of the period.
     *
     * @return list<array<string, mixed>>
     */
    private function months(Selection $s, User $user): array
    {
        $byMonth = $this->data->keyed($this->data->returns($s, $user, ['month']), 'month');
        $running = ['tested' => 0, 'positive' => 0, 'started' => 0];
        $points = [];

        foreach ($s->months() as $month) {
            foreach (array_keys($running) as $key) {
                $running[$key] += (int) ($byMonth[$month][$key] ?? 0);
            }

            $points[] = [
                'month' => $month,
                'label' => Labels::month($month),
                'tested' => $running['tested'],
                'positive' => $running['positive'],
                'started_treatment' => $running['started'],
            ];
        }

        return $points;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $key, string $label, mixed $value, string $missing = 'not_enough_returns'): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value] + ($value === null ? ['status' => $missing] : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function ring(string $key, string $label, mixed $value, int $max, string $missing, ?string $change = null): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'max' => $max, 'unit' => '%']
            + ($value === null ? ['status' => $missing] : [])
            + ($change === null ? [] : ['change' => $change]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tile(string $key, string $label, mixed $value, ?string $unit, string $missing = 'not_enough_returns', ?string $status = null): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'unit' => $unit]
            + ($value === null ? ['status' => $missing] : ($status !== null ? ['status' => $status] : []));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $key, string $icon, string $label, string $meaning, mixed $value, ?string $unit, string $missing = 'not_enough_returns', ?string $status = null, ?string $change = null): array
    {
        return ['key' => $key, 'icon' => $icon, 'label' => $label, 'meaning' => $meaning, 'value' => $value, 'unit' => $unit]
            + ($value === null ? ['status' => $missing] : ($status !== null ? ['status' => $status] : []))
            + ($change === null ? [] : ['change' => $change]);
    }
}

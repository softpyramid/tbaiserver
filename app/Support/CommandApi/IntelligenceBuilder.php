<?php

namespace App\Support\CommandApi;

use App\Models\User;

final class IntelligenceBuilder
{
    public function __construct(private CommandData $data, private AlertEngine $alerts) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Selection $s, User $user): array
    {
        $alerts = $this->alerts->evaluate($s, $user);
        $districts = $this->data->districts($s, $user);

        return [
            'alert_center' => [
                'lines' => $this->alerts->lines($alerts),
                'all' => $alerts,
            ],
            'top8' => [
                $this->chart($districts, 'positivity', 'positivity', 'Positivity rate', 'highest 8 districts', 'positivity', 0, 1, '%'),
                $this->chart($districts, 'tat', 'median_tat', 'Median diagnostic TAT', 'slowest 8 districts', 'tat_days', 1, 1, 'd'),
                $this->chart($districts, 'treatment_gap', 'treatment_gap', 'Treatment gap', 'top 8 districts', 'treatment_gap', 0, 0, null),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $districts
     * @return array<string, mixed>
     */
    private function chart(array $districts, string $key, string $indicator, string $title, string $scope, string $field, int $axis, int $decimals, ?string $unit): array
    {
        $ranked = array_values(array_filter($districts, fn (array $d): bool => $d[$field] !== null));
        usort($ranked, fn (array $a, array $b): int => [$b[$field], $a['name']] <=> [$a[$field], $b['name']]);

        return [
            'key' => $key,
            'metric' => $indicator,
            'title_subject' => $title,
            'scope' => $scope,
            'indicator' => $indicator,
            'axis_decimals' => $axis,
            'value_decimals' => $decimals,
            'unit' => $unit,
            'items' => array_map(fn (array $d): array => [
                'city_id' => $d['city_id'],
                'name' => $d['name'],
                'short_name' => $d['short_name'],
                'value' => CommandData::number($d[$field], $decimals),
                'decimals' => $decimals,
            ], array_slice($ranked, 0, 8)),
        ];
    }
}

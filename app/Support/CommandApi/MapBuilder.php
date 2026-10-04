<?php

namespace App\Support\CommandApi;

use App\Models\User;
use App\Support\CommandPeriod;
use App\Support\DistrictMatch;

final class MapBuilder
{
    public function __construct(private CommandData $data) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Selection $s, User $user, string $indicator, ?int $selected): array
    {
        $indicators = Labels::indicators();
        $indicator = isset($indicators[$indicator]) ? $indicator : 'positivity';
        $meta = $indicators[$indicator];
        $districts = $this->data->districts($s, $user);
        $trend = $this->trend($s, $user);
        $maxPositive = 0;

        foreach ($districts as $d) {
            $maxPositive = max($maxPositive, (int) $d['positive']);
        }

        $rows = [];

        foreach ($districts as $d) {
            $value = $d[$meta['field']];
            $rows[] = [
                'city_id' => $d['city_id'],
                'match_key' => $d['match_key'],
                'name' => $d['name'],
                'short_name' => $d['short_name'],
                'seat' => $d['seat'],
                'suspects' => $d['suspects'],
                'tested' => $d['tested'],
                'positive' => $d['positive'],
                'positivity' => $d['positivity'],
                'started_pct' => $d['started_pct'],
                'success_pct' => $d['success_pct'],
                'ltfu_pct' => $d['ltfu_pct'],
                'tat_days' => $d['tat_days'],
                'mdr_rr' => $d['mdr_rr'],
                'ai_tests' => $d['ai_tests'],
                'bmus' => $d['bmus'],
                'treatment_gap' => $d['treatment_gap'],
                'coverage_pct' => $d['coverage_pct'],
                'value' => $value,
                'heat' => $maxPositive > 0 ? round((int) $d['positive'] / $maxPositive, 3) : 0.0,
                'trend12' => $trend['values'][$d['city_id']] ?? array_fill(0, 12, 0),
            ];
        }

        $values = array_values(array_filter(array_column($rows, 'value'), fn (mixed $v): bool => $v !== null));
        $ranked = array_values(array_filter($rows, fn (array $r): bool => $r['value'] !== null));
        usort($ranked, fn (array $a, array $b): int => $meta['lowest_is_worst'] ? $a['value'] <=> $b['value'] : $b['value'] <=> $a['value']);

        $byPositive = $rows;
        usort($byPositive, fn (array $a, array $b): int => $b['positive'] <=> $a['positive']);

        $visibleKeys = array_column($rows, 'city_id');
        $all = $this->data->cities($s->withSector(null), $user);
        $hidden = [];

        foreach ($all as $city) {
            if (! in_array($city->id, $visibleKeys, true)) {
                $hidden[] = DistrictMatch::key($city->name);
            }
        }

        return [
            'indicators' => array_map(
                fn (string $key, array $i): array => ['key' => $key, 'label' => $i['label'], 'format' => $i['format'], 'lowest_is_worst' => $i['lowest_is_worst']],
                array_keys($indicators),
                $indicators,
            ),
            'sectors' => ['all', 'public', 'private', 'ppm'],
            'scale' => ['min' => $values === [] ? null : min($values), 'max' => $values === [] ? null : max($values)],
            'districts' => $rows,
            'top5' => array_map(
                fn (array $r): array => ['city_id' => $r['city_id'], 'name' => $r['name'], 'value' => CommandData::number($r['value'], $meta['decimals'])],
                array_slice($ranked, 0, 5),
            ),
            'pulse_top3' => array_map(fn (array $r): int => $r['city_id'], array_slice($byPositive, 0, 3)),
            'hidden' => $hidden,
            'selected' => $selected !== null && in_array($selected, $visibleKeys, true) ? $selected : null,
            'trend_months' => $trend['months'],
            'geometry' => [
                'url' => '/geo/sindh-districts.geojson',
                'match_property' => 'match',
                'indicative' => true,
                'attribution' => 'District boundaries: OCHA COD-AB, CC BY-IGO, simplified. Indicative only.',
            ],
            'schematic' => [
                'outline' => DistrictSites::OUTLINE,
                'note' => "The prototype's hand-drawn outline and district seat points, for an exact schematic (Voronoi) copy. Seat points are each district's `seat` [lon, lat].",
            ],
        ];
    }

    /**
     * Positives per month for the 12 months ending at the period end.
     *
     * @return array{months: list<string>, values: array<int, list<int>>}
     */
    private function trend(Selection $s, User $user): array
    {
        $window = CommandPeriod::trailingMonths($s->to, 12);
        $trend = $s->withPeriod($window->from, $s->to);
        $months = $trend->months();
        $by = [];

        foreach ($this->data->returns($trend, $user, ['city_id', 'month']) as $row) {
            $by[(int) $row['city_id']][$row['month']] = (int) $row['positive'];
        }

        $values = [];

        foreach ($by as $cityId => $perMonth) {
            $values[$cityId] = array_map(fn (string $month): int => $perMonth[$month] ?? 0, $months);
        }

        return ['months' => $months, 'values' => $values];
    }
}

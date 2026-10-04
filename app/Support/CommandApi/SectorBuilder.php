<?php

namespace App\Support\CommandApi;

use App\Models\City;
use App\Models\User;
use App\Support\DistrictMatch;

final class SectorBuilder
{
    public function __construct(private CommandData $data) {}

    /**
     * @return array<string, mixed>
     */
    public function table(Selection $s, User $user, string $sort, string $direction): array
    {
        $rows = array_map(fn (array $d): array => $this->row($d), $this->data->districts($s, $user));
        $descending = $direction === 'desc';

        usort($rows, function (array $a, array $b) use ($sort, $descending): int {
            if ($sort === 'district') {
                $order = strcasecmp((string) $a['district'], (string) $b['district']);

                return $descending ? -$order : $order;
            }

            $left = $a[$sort];
            $right = $b[$sort];

            if ($left === null || $right === null) {
                if ($left === $right) {
                    return strcasecmp((string) $a['district'], (string) $b['district']);
                }

                return $left === null ? 1 : -1;
            }

            $order = $left <=> $right;

            return $order !== 0 ? ($descending ? -$order : $order) : strcasecmp((string) $a['district'], (string) $b['district']);
        });

        $total = $this->data->totals($s, $user);

        return [
            'columns' => [
                $this->column('district', 'District', 'left', ['type' => 'text']),
                $this->column('suspects', 'Suspects', 'right', ['type' => 'int']),
                $this->column('tested', 'Tested', 'right', ['type' => 'int']),
                $this->column('positive', 'Positive', 'right', ['type' => 'int']),
                $this->column('positivity', 'Positivity', 'right', ['type' => 'percent', 'decimals' => 1]),
                $this->column('started_pct', 'Started tx', 'right', ['type' => 'percent', 'decimals' => 0]),
                $this->column('success_pct', 'Success', 'right', ['type' => 'percent', 'decimals' => 0]),
                $this->column('ltfu_pct', 'LTFU', 'right', ['type' => 'percent', 'decimals' => 1]),
                $this->column('tat_days', 'TAT (d)', 'right', ['type' => 'decimal', 'decimals' => 1]),
                $this->column('mdr_rr', 'MDR/RR', 'right', ['type' => 'int']),
                $this->column('ai_tests', 'AI tests', 'right', ['type' => 'int']),
                $this->column('bmus', 'BMUs', 'right', ['type' => 'int']),
            ],
            'rows' => $rows,
            'total' => ['city_id' => null, 'district' => $s->scopeName, 'match_key' => null] + $this->figures($total),
            'row_action' => $s->sector?->value === 'ppm' ? 'map' : 'bmu_drilldown',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bmus(Selection $s, User $user, City $city): array
    {
        $sector = $s->sector;
        $facilities = $this->data->facilities($s, $user)->with('team')->where('facilities.status', 'active')->get();
        $returns = $this->data->keyed($this->data->returns($s, $user, ['facility_id']), 'facility_id');
        $outcomes = $this->data->keyed($this->data->monthly('treatment_outcomes', ['cohort_size', 'successful', 'lost_to_follow_up'], $s, $user, ['facility_id']), 'facility_id');
        $byMonth = $this->data->keyed($this->data->returns($s, $user, ['facility_id', 'month']), 'facility_id', 'month');
        $districtPositive = (int) ($this->data->returns($s, $user)[0]['positive'] ?? 0);
        $empty = ['suspects' => 0, 'tested' => 0, 'positive' => 0, 'negative' => 0, 'started' => 0, 'tat_weighted' => 0, 'tat_weight' => 0, 'active' => 0, 'active_bmus' => 0];
        $items = [];

        foreach ($facilities as $facility) {
            $key = (string) $facility->id;

            if (! isset($returns[$key])) {
                continue;
            }

            $m = $this->data->metrics($returns[$key], $outcomes[$key] ?? null, null, null);
            $months = [];

            foreach ($s->months() as $month) {
                $cell = $byMonth[$key.'|'.$month] ?? $empty;
                $months[] = [
                    'month' => $month,
                    'label' => Labels::month($month),
                    'tested' => (int) $cell['tested'],
                    'positive' => (int) $cell['positive'],
                ];
            }

            $label = DistrictSites::facilityLabel($facility->code, $facility->team->name);
            $items[] = [
                'facility_id' => $facility->id,
                'name' => $facility->team->name,
                'label' => $label,
                'suspects' => $m['suspects'],
                'tested' => $m['tested'],
                'positive' => $m['positive'],
                'positivity' => $m['positivity'],
                'success_pct' => $m['success_pct'],
                'ltfu_pct' => $m['ltfu_pct'],
                'tat_days' => $m['tat_days'],
                'share_of_district_positive' => $districtPositive > 0 ? (int) round($m['positive'] / $districtPositive * 100) : null,
                'months' => $months,
            ];
        }

        usort($items, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return [
            'district' => [
                'city_id' => $city->id,
                'name' => $city->name,
                'match_key' => DistrictMatch::key($city->name),
            ],
            'summary' => [
                'active_bmus' => count($items),
                'suspects' => array_sum(array_column($items, 'suspects')),
                'tested' => array_sum(array_column($items, 'tested')),
                'positive' => array_sum(array_column($items, 'positive')),
            ],
            'bmus' => $items,
            'map' => ['sector' => $sector->value ?? 'all', 'indicator' => 'positivity', 'selected' => $city->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function row(array $d): array
    {
        return [
            'city_id' => $d['city_id'],
            'district' => $d['name'],
            'match_key' => $d['match_key'],
        ] + $this->figures($d);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function figures(array $d): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $format
     * @return array<string, mixed>
     */
    private function column(string $key, string $label, string $align, array $format): array
    {
        return ['key' => $key, 'label' => $label, 'align' => $align, 'format' => $format];
    }
}

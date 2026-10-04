<?php

namespace App\Support\CommandApi;

use App\Models\City;
use App\Models\User;
use App\Support\DistrictMatch;
use Illuminate\Support\Facades\DB;

final class SectorBuilder
{
    public function __construct(private CommandData $data) {}

    /**
     * @return array<string, mixed>
     */
    public function table(Selection $s, User $user, string $sort, string $direction): array
    {
        $districts = $this->data->districts($s, $user);
        $overlay = $s->sector !== null ? $this->overlay($s->sector->value, array_column($districts, 'city_id')) : [];
        $districts = array_map(fn (array $d): array => isset($overlay[$d['city_id']]) ? $overlay[$d['city_id']] + $d : $d, $districts);
        $rows = array_map(fn (array $d): array => $this->row($d), $districts);
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

        // Every row drawn from the overlay: the total is the tables' own (sums
        // and plain means of the district figures). Otherwise it is computed.
        $total = $overlay !== [] && count($overlay) === count($districts)
            ? $this->overlayTotal(array_values($overlay))
            : $this->data->totals($s, $user);

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

        $published = $sector !== null ? ($this->overlay($sector->value, [$city->id])[$city->id] ?? null) : null;

        if ($published !== null) {
            $items = $this->fitToPublished($items, $published);
        }

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
     * The seeded sector rows for these districts, keyed by city id and shaped
     * like a district row. Empty when the table has none (production).
     *
     * @param  list<int>  $cityIds
     * @return array<int, array<string, mixed>>
     */
    private function overlay(string $sector, array $cityIds): array
    {
        if ($cityIds === []) {
            return [];
        }

        $rows = [];

        foreach (DB::table('sector_indicators')->where('sector', $sector)->whereIn('city_id', $cityIds)->get() as $r) {
            $positive = (int) $r->positive;
            $rows[(int) $r->city_id] = [
                'suspects' => (int) $r->suspects,
                'tested' => (int) $r->tested,
                'positive' => $positive,
                'positivity' => round((float) $r->positivity, 4),
                'started_pct' => $positive > 0 ? round((int) $r->started_treatment / $positive * 100, 4) : 0.0,
                'success_pct' => round((float) $r->success_pct, 4),
                'ltfu_pct' => round((float) $r->ltfu_pct, 4),
                'tat_days' => round((float) $r->tat_days, 4),
                'mdr_rr' => (int) $r->mdr_rr,
                // Every AI figure disappears with the flag, overlay or not.
                'ai_tests' => config('tbai.features.ai_tests') ? (int) $r->ai_tests : null,
                'bmus' => (int) $r->bmus,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function overlayTotal(array $rows): array
    {
        $sum = fn (string $key): ?int => in_array(null, array_column($rows, $key), true) ? null : (int) array_sum(array_column($rows, $key));
        $mean = fn (string $key): float => round(array_sum(array_column($rows, $key)) / count($rows), 4);

        return [
            'suspects' => $sum('suspects'),
            'tested' => $sum('tested'),
            'positive' => $sum('positive'),
            'positivity' => $mean('positivity'),
            'started_pct' => $mean('started_pct'),
            'success_pct' => $mean('success_pct'),
            'ltfu_pct' => $mean('ltfu_pct'),
            'tat_days' => $mean('tat_days'),
            'mdr_rr' => $sum('mdr_rr'),
            'ai_tests' => $sum('ai_tests'),
            'bmus' => $sum('bmus'),
        ];
    }

    /**
     * The BMU list of a district whose row comes from the overlay: each site
     * keeps its share, scaled so the list adds up to the row the user clicked.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $published
     * @return list<array<string, mixed>>
     */
    private function fitToPublished(array $items, array $published): array
    {
        if ($items === []) {
            return $items;
        }

        $parts = [];

        foreach (['suspects', 'tested', 'positive'] as $key) {
            $parts[$key] = Apportion::split((int) $published[$key], array_map(fn (array $i): int => (int) $i[$key], $items));
        }

        foreach ($items as $n => $item) {
            foreach (['tested', 'positive'] as $key) {
                $weights = array_column($item['months'], $key);

                foreach (Apportion::split($parts[$key][$n], $weights) as $m => $value) {
                    $items[$n]['months'][$m][$key] = $value;
                }
            }

            $items[$n]['suspects'] = $parts['suspects'][$n];
            $items[$n]['tested'] = $parts['tested'][$n];
            $items[$n]['positive'] = $parts['positive'][$n];
            $items[$n]['positivity'] = $parts['tested'][$n] > 0 ? round($parts['positive'][$n] / $parts['tested'][$n] * 100, 4) : null;
            $items[$n]['share_of_district_positive'] = (int) $published['positive'] > 0 ? (int) round($parts['positive'][$n] / (int) $published['positive'] * 100) : null;
        }

        return $items;
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

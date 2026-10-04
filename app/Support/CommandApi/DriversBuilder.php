<?php

namespace App\Support\CommandApi;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DriversBuilder
{
    private const VARIABLES = [
        ['diagnostic_coverage', 'diagnostic coverage'],
        ['testing_delay', 'testing delay'],
        ['positivity', 'positivity'],
        ['treatment_initiation', 'treatment initiation'],
        ['treatment_interruption', 'treatment interruption'],
        ['contact_evaluation', 'contact evaluation'],
        ['hiv_testing', 'HIV testing'],
        ['population_density', 'population density'],
        ['malnutrition', 'malnutrition indicators where available'],
        ['socioeconomic_environmental', 'socioeconomic/environmental variables where available'],
        ['access_distance', 'access/distance to services'],
    ];

    /** letter, key, label, indicator, signals */
    private const CATEGORIES = [
        ['A', 'diagnostic_bottlenecks', 'Diagnostic bottlenecks', 'median_tat', ['testing_delay', 'diagnostic_coverage']],
        ['B', 'unusual_positivity', 'Unusual positivity', 'positivity', ['positivity']],
        ['C', 'missing_patients', 'Missing patients', 'treatment_gap', ['treatment_initiation']],
        ['D', 'follow_up_risk', 'Follow-up risk', 'lost_to_follow_up', ['treatment_interruption', 'contact_evaluation']],
        ['E', 'testing_gaps', 'Testing gaps', 'testing_coverage', ['diagnostic_coverage', 'hiv_testing', 'access_distance']],
        ['F', 'resource_allocation', 'Resource allocation', 'suspects', ['population_density', 'malnutrition', 'socioeconomic_environmental']],
    ];

    public function __construct(private CommandData $data) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Selection $s, User $user): array
    {
        $districts = $this->data->districts($s, $user);
        $total = $this->data->totals($s, $user);
        $interventions = (bool) config('tbai.features.interventions');
        $responses = (array) config('tbai.command.driver_responses', []);
        $indicators = Labels::indicators() + [
            'testing_coverage' => ['field' => 'coverage_pct', 'lowest_is_worst' => true, 'decimals' => 0],
        ];
        $available = $this->availability($s, $user, $total);

        return [
            'variables' => array_map(
                fn (array $v): array => ['key' => $v[0], 'label' => $v[1], 'available' => $available[$v[0]] ?? false],
                self::VARIABLES,
            ),
            'categories' => array_map(function (array $c) use ($districts, $indicators, $interventions, $responses): array {
                $indicator = $indicators[$c[3]];
                $field = $indicator['field'];
                $ranked = array_values(array_filter($districts, fn (array $d): bool => $d[$field] !== null));
                usort($ranked, fn (array $a, array $b): int => $indicator['lowest_is_worst']
                    ? [$a[$field], $a['name']] <=> [$b[$field], $b['name']]
                    : [$b[$field], $a['name']] <=> [$a[$field], $b['name']]);

                return [
                    'letter' => $c[0],
                    'key' => $c[1],
                    'label' => $c[2],
                    'indicator' => $c[3],
                    'lowest_is_worst' => $indicator['lowest_is_worst'],
                    'signals' => $c[4],
                    'responses' => $interventions ? array_values((array) ($responses[$c[1]] ?? [])) : [],
                    'priority' => $interventions ? array_map(fn (array $d): array => [
                        'city_id' => $d['city_id'],
                        'name' => $d['name'],
                        'value' => CommandData::number($d[$field], $indicator['decimals']),
                    ], array_slice($ranked, 0, 3)) : [],
                ];
            }, self::CATEGORIES),
            'default_category' => 'A',
        ];
    }

    /**
     * @param  array<string, mixed>  $total
     * @return array<string, bool>
     */
    private function availability(Selection $s, User $user, array $total): array
    {
        $contacts = $this->data->monthly('contact_tracing', ['contacts_listed'], $s, $user)[0];
        $hiv = $this->data->monthly('hiv_testing', ['tb_patients'], $s, $user)[0];
        $cities = array_map(fn ($c) => $c->id, $this->data->cities($s, $user));
        $indicator = fn (string $column): bool => $cities !== []
            && DB::table('district_indicators')->whereIn('city_id', $cities)->whereNotNull($column)->exists();

        return [
            'diagnostic_coverage' => $total['suspects'] > 0,
            'testing_delay' => $total['tat_days'] !== null,
            'positivity' => $total['positivity'] !== null,
            'treatment_initiation' => $total['positive'] > 0,
            'treatment_interruption' => $total['ltfu_pct'] !== null,
            'contact_evaluation' => (int) $contacts['record_count'] > 0,
            'hiv_testing' => (int) $hiv['record_count'] > 0,
            'population_density' => $indicator('density_per_km2'),
            'malnutrition' => $indicator('malnutrition_pct'),
            'socioeconomic_environmental' => $indicator('socioeconomic_index'),
            'access_distance' => $indicator('avg_distance_km'),
        ];
    }
}

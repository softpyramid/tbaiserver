<?php

namespace Database\Seeders;

use App\Enums\AlertComparator;
use App\Enums\AlertMetric;
use App\Enums\AlertSubject;
use App\Models\AlertRule;
use Database\Seeders\Concerns\SkipsOutsideLocalDevStaging;
use Illuminate\Database\Seeder;

class AlertRuleSeeder extends Seeder
{
    use SkipsOutsideLocalDevStaging;

    /**
     * Demo alert rules for local, dev, and staging.
     *
     * Safe to run again. Skips production and every other environment.
     * Thresholds are placeholders until the TB programme confirms them.
     *
     *   php artisan db:seed --class=AlertRuleSeeder
     */
    public function run(): void
    {
        if ($this->skipsOutsideLocalDevStaging()) {
            return;
        }

        foreach ($this->rules() as $rule) {
            AlertRule::query()->updateOrCreate(
                ['key' => $rule['key']],
                $rule,
            );
        }
    }

    /**
     * @return list<array{
     *     key: string,
     *     metric: AlertMetric,
     *     comparator: AlertComparator,
     *     threshold: float,
     *     severity: string,
     *     label: string,
     *     subject: AlertSubject
     * }>
     */
    private function rules(): array
    {
        return [
            [
                'key' => 'positives_without_treatment',
                'metric' => AlertMetric::TreatmentGap,
                'comparator' => AlertComparator::Gt,
                'threshold' => 0,
                'severity' => 'critical',
                'label' => 'Positives with no treatment start',
                'subject' => AlertSubject::District,
            ],
            [
                'key' => 'facility_turnaround_up',
                'metric' => AlertMetric::TatRatio,
                'comparator' => AlertComparator::Gte,
                'threshold' => 2.3,
                'severity' => 'attention',
                'label' => 'Facility turnaround up 2.3x',
                'subject' => AlertSubject::Facility,
            ],
            [
                'key' => 'positivity_rising',
                'metric' => AlertMetric::PositivityStreak,
                'comparator' => AlertComparator::Gte,
                'threshold' => 3,
                'severity' => 'emerging',
                'label' => 'Positivity rising 3 periods in a row',
                'subject' => AlertSubject::District,
            ],
            [
                'key' => 'turnaround_improvement',
                'metric' => AlertMetric::TatRatio,
                'comparator' => AlertComparator::Lt,
                'threshold' => 1,
                'severity' => 'improvement',
                'label' => 'Turnaround improvement',
                'subject' => AlertSubject::District,
            ],
        ];
    }
}

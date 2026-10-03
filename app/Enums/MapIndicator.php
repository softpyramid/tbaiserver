<?php

namespace App\Enums;

use App\Support\CommandTotals;

enum MapIndicator: string
{
    case Suspects = 'suspects';
    case Positivity = 'positivity';
    case ProvisionalTat = 'provisional_tat';
    case TreatmentGap = 'treatment_gap';
    case TestingCoverage = 'testing_coverage';

    public function label(): string
    {
        return match ($this) {
            self::Suspects => __('entry.suspects'),
            self::Positivity => __('entry.positivity_rate'),
            self::ProvisionalTat => __('entry.column_tat_provisional'),
            self::TreatmentGap => __('entry.column_treatment_gap'),
            self::TestingCoverage => __('entry.testing_coverage'),
        };
    }

    /**
     * @param  array{
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     started_treatment: int,
     *     positivity: float|null,
     *     treatment_gap: int,
     *     provisional_tat: float|null
     * }  $summary
     */
    public function read(array $summary): int|float|null
    {
        return match ($this) {
            self::Suspects => $summary['suspects'],
            self::Positivity => $summary['positivity'],
            self::ProvisionalTat => $summary['provisional_tat'],
            self::TreatmentGap => $summary['treatment_gap'],
            self::TestingCoverage => CommandTotals::testingCoverage($summary['tested'], $summary['suspects']),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $indicator): array => [
                'value' => $indicator->value,
                'label' => $indicator->label(),
            ],
            self::cases(),
        );
    }
}

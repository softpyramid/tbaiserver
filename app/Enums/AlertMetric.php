<?php

namespace App\Enums;

enum AlertMetric: string
{
    case TreatmentGap = 'treatment_gap';
    case TatRatio = 'tat_ratio';
    case PositivityStreak = 'positivity_streak';

    public function label(): string
    {
        return match ($this) {
            self::TreatmentGap => __('entry.alert_metric_treatment_gap'),
            self::TatRatio => __('entry.alert_metric_tat_ratio'),
            self::PositivityStreak => __('entry.alert_metric_positivity_streak'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $metric): array => [
                'value' => $metric->value,
                'label' => $metric->label(),
            ],
            self::cases(),
        );
    }
}

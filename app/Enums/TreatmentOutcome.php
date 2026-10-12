<?php

namespace App\Enums;

/**
 * Treatment outcome, as in the TB03 register.
 */
enum TreatmentOutcome: string
{
    case Cured = 'cured';
    case TreatmentCompleted = 'treatment_completed';
    case TreatmentFailed = 'treatment_failed';
    case Died = 'died';
    case LostToFollowUp = 'lost_to_follow_up';
    case NotEvaluated = 'not_evaluated';

    public function label(): string
    {
        return match ($this) {
            self::Cured => 'Cured',
            self::TreatmentCompleted => 'Treatment completed',
            self::TreatmentFailed => 'Treatment failed',
            self::Died => 'Died',
            self::LostToFollowUp => 'Lost to follow-up',
            self::NotEvaluated => 'Not evaluated',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

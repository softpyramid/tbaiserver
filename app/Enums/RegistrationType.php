<?php

namespace App\Enums;

/**
 * Type of registration, as in the TB03 register.
 */
enum RegistrationType: string
{
    case New = 'new';
    case Relapse = 'relapse';
    case TreatmentAfterFailure = 'treatment_after_failure';
    case TreatmentAfterLossToFollowUp = 'treatment_after_loss_to_follow_up';
    case TransferIn = 'transfer_in';
    case OtherPreviouslyTreated = 'other_previously_treated';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Relapse => 'Relapse',
            self::TreatmentAfterFailure => 'Treatment after failure',
            self::TreatmentAfterLossToFollowUp => 'Treatment after loss to follow-up',
            self::TransferIn => 'Transfer in',
            self::OtherPreviouslyTreated => 'Other previously treated',
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

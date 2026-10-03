<?php

namespace App\Enums;

/**
 * Drivers named in the discovery note that have no daily-return field.
 */
enum PendingDriver: string
{
    case HivTesting = 'hiv_testing';
    case ContactEvaluation = 'contact_evaluation';
    case Malnutrition = 'malnutrition';
    case Distance = 'distance';
    case Socioeconomic = 'socioeconomic';

    public function label(): string
    {
        return match ($this) {
            self::HivTesting => __('entry.pending_hiv_testing'),
            self::ContactEvaluation => __('entry.pending_contact_evaluation'),
            self::Malnutrition => __('entry.pending_malnutrition'),
            self::Distance => __('entry.pending_distance'),
            self::Socioeconomic => __('entry.pending_socioeconomic'),
        };
    }
}

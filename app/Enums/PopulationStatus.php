<?php

namespace App\Enums;

enum PopulationStatus: string
{
    case Confirmed = 'confirmed';
    case Placeholder = 'placeholder';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => __('entry.population_status_confirmed'),
            self::Placeholder => __('entry.population_status_placeholder'),
        };
    }
}

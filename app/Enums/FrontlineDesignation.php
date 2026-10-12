<?php

namespace App\Enums;

/**
 * What a frontline worker (team role screener) does at the facility.
 * Only meaningful for screeners; every other team role stores null.
 */
enum FrontlineDesignation: string
{
    case Doctor = 'doctor';
    case Lhw = 'lhw';
    case Chw = 'chw';
    case Nurse = 'nurse';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Doctor => 'Doctor',
            self::Lhw => 'LHW',
            self::Chw => 'CHW',
            self::Nurse => 'Nurse',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $designation): array => ['value' => $designation->value, 'label' => $designation->label()],
            self::cases(),
        );
    }
}

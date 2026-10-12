<?php

namespace App\Enums;

enum DiseaseSite: string
{
    case Pulmonary = 'pulmonary';
    case Extrapulmonary = 'extrapulmonary';

    public function label(): string
    {
        return match ($this) {
            self::Pulmonary => 'Pulmonary',
            self::Extrapulmonary => 'Extrapulmonary',
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

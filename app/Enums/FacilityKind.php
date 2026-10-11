<?php

namespace App\Enums;

enum FacilityKind: string
{
    case Bmu = 'bmu';
    case Hospital = 'hospital';
    case Clinic = 'clinic';

    public function label(): string
    {
        return match ($this) {
            self::Bmu => 'BMU',
            self::Hospital => 'Hospital Labs/IDTF',
            self::Clinic => 'Clinic',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ],
            self::cases(),
        );
    }
}

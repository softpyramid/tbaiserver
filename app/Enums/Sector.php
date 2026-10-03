<?php

namespace App\Enums;

enum Sector: string
{
    case Public = 'public';
    case Private = 'private';
    case Ppm = 'ppm';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Ppm => 'PPM',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $sector): array => [
                'value' => $sector->value,
                'label' => $sector->label(),
            ],
            self::cases(),
        );
    }
}

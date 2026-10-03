<?php

namespace App\Enums;

enum CommandCenterScreen: string
{
    case Cumulative = 'cumulative';
    case Intelligence = 'intelligence';
    case Public = 'public';
    case Private = 'private';
    case Ppm = 'ppm';
    case Drivers = 'drivers';
    case Map = 'map';
    case Assisted = 'assisted';
    case Incidence = 'incidence';

    public function label(): string
    {
        return match ($this) {
            self::Cumulative => 'Cumulative',
            self::Intelligence => 'Intelligence',
            self::Public => 'Public',
            self::Private => 'Private',
            self::Ppm => 'PPM',
            self::Drivers => 'Drivers',
            self::Map => 'Map',
            self::Assisted => 'AI Assisted Testing',
            self::Incidence => 'Incidence',
        };
    }

    public function sector(): ?Sector
    {
        return match ($this) {
            self::Public => Sector::Public,
            self::Private => Sector::Private,
            self::Ppm => Sector::Ppm,
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $screen): array => [
                'value' => $screen->value,
                'label' => $screen->label(),
            ],
            self::cases(),
        );
    }
}

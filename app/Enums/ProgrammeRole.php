<?php

namespace App\Enums;

enum ProgrammeRole: string
{
    case TbaiAdmin = 'tbai_admin';
    case CountryViewer = 'country_viewer';
    case ProvincialViewer = 'provincial_viewer';
    case CityOfficer = 'city_officer';

    /**
     * Geographic scope required for this role.
     * TB-AI admin is unscoped.
     */
    public function scopeType(): ?ScopeType
    {
        return match ($this) {
            self::TbaiAdmin => null,
            self::CountryViewer => ScopeType::Country,
            self::ProvincialViewer => ScopeType::Province,
            self::CityOfficer => ScopeType::City,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TbaiAdmin => 'TB-AI admin',
            self::CountryViewer => 'Country viewer',
            self::ProvincialViewer => 'Provincial viewer',
            self::CityOfficer => 'City officer',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            self::cases(),
        );
    }
}

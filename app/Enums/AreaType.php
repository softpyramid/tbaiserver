<?php

namespace App\Enums;

enum AreaType: string
{
    case District = 'district';
    case Province = 'province';

    public function label(): string
    {
        return match ($this) {
            self::District => __('entry.column_district'),
            self::Province => __('entry.province'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}

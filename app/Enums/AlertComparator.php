<?php

namespace App\Enums;

enum AlertComparator: string
{
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';

    public function label(): string
    {
        return match ($this) {
            self::Gt => __('entry.alert_comparator_gt'),
            self::Gte => __('entry.alert_comparator_gte'),
            self::Lt => __('entry.alert_comparator_lt'),
            self::Lte => __('entry.alert_comparator_lte'),
        };
    }

    public function matches(float $value, float $threshold): bool
    {
        return match ($this) {
            self::Gt => $value > $threshold,
            self::Gte => $value >= $threshold,
            self::Lt => $value < $threshold,
            self::Lte => $value <= $threshold,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $comparator): array => [
                'value' => $comparator->value,
                'label' => $comparator->label(),
            ],
            self::cases(),
        );
    }
}

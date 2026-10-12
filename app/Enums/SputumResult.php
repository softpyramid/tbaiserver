<?php

namespace App\Enums;

/**
 * Follow-up sputum result recorded at a monthly visit.
 */
enum SputumResult: string
{
    case Negative = 'negative';
    case Positive = 'positive';
    case NotDone = 'not_done';

    public function label(): string
    {
        return match ($this) {
            self::Negative => 'Negative',
            self::Positive => 'Positive',
            self::NotDone => 'Not done',
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

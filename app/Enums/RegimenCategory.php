<?php

namespace App\Enums;

/**
 * Treatment regimen category, as on the TB01 treatment card.
 */
enum RegimenCategory: string
{
    case CategoryINew = 'category_i_new';
    case CategoryIiRetreatment = 'category_ii_retreatment';
    case DrTb = 'dr_tb';
    case Child = 'child';

    public function label(): string
    {
        return match ($this) {
            self::CategoryINew => 'Category I (new)',
            self::CategoryIiRetreatment => 'Category II (retreatment)',
            self::DrTb => 'DR-TB',
            self::Child => 'Childhood TB',
        };
    }

    /**
     * Monthly follow-up visits scheduled when treatment starts.
     *
     * Provisional placeholders until Hira confirms them against the NTP
     * manual: 6 for category I and childhood TB, 8 for category II
     * retreatment, 9 for DR-TB (shorter regimen; longer DR-TB regimens need
     * more visits, which staff can follow outside this schedule for now).
     */
    public function visitCount(): int
    {
        return match ($this) {
            self::CategoryINew, self::Child => 6,
            self::CategoryIiRetreatment => 8,
            self::DrTb => 9,
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

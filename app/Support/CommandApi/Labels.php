<?php

namespace App\Support\CommandApi;

/**
 * Wording the prototype uses for months and indicators.
 */
final class Labels
{
    private const MONTHS = [
        '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APRIL', '05' => 'MAY', '06' => 'JUN',
        '07' => 'JUL', '08' => 'AUG', '09' => 'SEP', '10' => 'OCT', '11' => 'NOV', '12' => 'DEC',
    ];

    /** The prototype writes APRIL in full and every other month in three letters. */
    public static function month(string $month): string
    {
        return self::MONTHS[substr($month, 5, 2)] ?? $month;
    }

    /**
     * @param  list<string>  $months
     * @return list<array{month: string, label: string}>
     */
    public static function months(array $months): array
    {
        return array_map(fn (string $month): array => ['month' => $month, 'label' => self::month($month)], $months);
    }

    /**
     * Map indicator keys to the district row field and display rules.
     *
     * @return array<string, array{field: string, label: string, format: array<string, mixed>, lowest_is_worst: bool, decimals: int}>
     */
    public static function indicators(): array
    {
        $all = [
            'suspects' => ['field' => 'suspects', 'label' => 'Suspects', 'format' => ['type' => 'int'], 'lowest_is_worst' => false, 'decimals' => 0],
            'positivity' => ['field' => 'positivity', 'label' => 'Positivity', 'format' => ['type' => 'percent', 'decimals' => 1], 'lowest_is_worst' => false, 'decimals' => 1],
            'median_tat' => ['field' => 'tat_days', 'label' => 'Median TAT', 'format' => ['type' => 'decimal', 'decimals' => 1, 'suffix' => ' d'], 'lowest_is_worst' => false, 'decimals' => 1],
            'lost_to_follow_up' => ['field' => 'ltfu_pct', 'label' => 'Lost to follow-up', 'format' => ['type' => 'percent', 'decimals' => 1], 'lowest_is_worst' => false, 'decimals' => 1],
            'treatment_gap' => ['field' => 'treatment_gap', 'label' => 'Treatment gap', 'format' => ['type' => 'int'], 'lowest_is_worst' => false, 'decimals' => 0],
            'testing_coverage' => ['field' => 'coverage_pct', 'label' => 'Testing coverage', 'format' => ['type' => 'percent', 'decimals' => 1], 'lowest_is_worst' => true, 'decimals' => 0],
            'notification_rate' => ['field' => 'notification_rate', 'label' => 'TB case notification rate', 'format' => ['type' => 'decimal', 'decimals' => 1, 'suffix' => ' per 100k'], 'lowest_is_worst' => false, 'decimals' => 1],
            'ai_tests' => ['field' => 'ai_tests', 'label' => 'AI-assisted tests', 'format' => ['type' => 'int'], 'lowest_is_worst' => false, 'decimals' => 0],
        ];

        if (! config('tbai.features.ai_tests')) {
            unset($all['ai_tests']);
        }

        return $all;
    }
}

<?php

namespace App\Support\Suspect;

/**
 * Evaluates a rule condition over answers keyed by question key.
 *
 * Shapes:
 *   {"eq": [key, value]}            the stored answer equals value
 *   {"all": [condition, ...]}       every condition holds
 *   {"any": [condition, ...]}       at least one condition holds
 *   {"count_yes": [[keys], n]}      at least n of the keys are answered yes
 *
 * A missing answer (not asked) and unknown never satisfy "eq ... yes" or
 * "eq ... no". Anything malformed is false, never true.
 */
class Condition
{
    /**
     * @param  array<array-key, mixed>|null  $condition
     * @param  array<string, string>  $answers
     */
    public static function holds(?array $condition, array $answers): bool
    {
        if ($condition === null || count($condition) !== 1) {
            return false;
        }

        $operator = array_key_first($condition);
        $operands = $condition[$operator];

        if (! is_array($operands)) {
            return false;
        }

        return match ($operator) {
            'eq' => self::eq($operands, $answers),
            'all' => self::all($operands, $answers),
            'any' => self::any($operands, $answers),
            'count_yes' => self::countYes($operands, $answers),
            default => false,
        };
    }

    /**
     * Question keys a condition reads, in the order they appear.
     *
     * @param  array<array-key, mixed>|null  $condition
     * @return list<string>
     */
    public static function keys(?array $condition): array
    {
        if ($condition === null || count($condition) !== 1) {
            return [];
        }

        $operator = array_key_first($condition);
        $operands = $condition[$operator];

        if (! is_array($operands)) {
            return [];
        }

        $keys = match ($operator) {
            'eq' => isset($operands[0]) && is_string($operands[0]) ? [$operands[0]] : [],
            'all', 'any' => array_merge(...array_map(
                fn (mixed $child): array => is_array($child) ? self::keys($child) : [],
                array_values($operands),
            )),
            'count_yes' => isset($operands[0]) && is_array($operands[0])
                ? array_values(array_filter($operands[0], 'is_string'))
                : [],
            default => [],
        };

        return array_values(array_unique($keys));
    }

    /**
     * The key and value pairs of every eq in a condition, for "asked because" text.
     *
     * @param  array<array-key, mixed>|null  $condition
     * @return list<array{0: string, 1: string}>
     */
    public static function equalities(?array $condition): array
    {
        if ($condition === null || count($condition) !== 1) {
            return [];
        }

        $operator = array_key_first($condition);
        $operands = $condition[$operator];

        if (! is_array($operands)) {
            return [];
        }

        if ($operator === 'eq') {
            return isset($operands[0], $operands[1]) && is_string($operands[0]) && is_string($operands[1])
                ? [[$operands[0], $operands[1]]]
                : [];
        }

        if ($operator === 'all' || $operator === 'any') {
            return array_merge(...array_map(
                fn (mixed $child): array => is_array($child) ? self::equalities($child) : [],
                array_values($operands),
            ));
        }

        return [];
    }

    /**
     * Plain text for admins, for example "cough_current is yes and cough_duration is gte_2_weeks".
     *
     * @param  array<array-key, mixed>|null  $condition
     */
    public static function describe(?array $condition): string
    {
        if ($condition === null || count($condition) !== 1) {
            return '';
        }

        $operator = array_key_first($condition);
        $operands = $condition[$operator];

        if (! is_array($operands)) {
            return '';
        }

        $children = fn (string $glue): string => implode($glue, array_filter(array_map(
            fn (mixed $child): string => is_array($child) ? self::describe($child) : '',
            array_values($operands),
        )));

        return match ($operator) {
            'eq' => is_string($operands[0] ?? null) && is_string($operands[1] ?? null)
                ? $operands[0].' is '.$operands[1]
                : '',
            'all' => $children(' and '),
            'any' => '('.$children(' or ').')',
            'count_yes' => is_array($operands[0] ?? null) && is_int($operands[1] ?? null)
                ? 'at least '.$operands[1].' yes of ('.implode(', ', array_filter($operands[0], 'is_string')).')'
                : '',
            default => '',
        };
    }

    /**
     * @param  array<array-key, mixed>  $operands
     * @param  array<string, string>  $answers
     */
    private static function eq(array $operands, array $answers): bool
    {
        $key = $operands[0] ?? null;
        $value = $operands[1] ?? null;

        if (! is_string($key) || ! is_string($value) || ! array_key_exists($key, $answers)) {
            return false;
        }

        return $answers[$key] === $value;
    }

    /**
     * @param  array<array-key, mixed>  $operands
     * @param  array<string, string>  $answers
     */
    private static function all(array $operands, array $answers): bool
    {
        if ($operands === []) {
            return false;
        }

        foreach ($operands as $child) {
            if (! is_array($child) || ! self::holds($child, $answers)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $operands
     * @param  array<string, string>  $answers
     */
    private static function any(array $operands, array $answers): bool
    {
        foreach ($operands as $child) {
            if (is_array($child) && self::holds($child, $answers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $operands
     * @param  array<string, string>  $answers
     */
    private static function countYes(array $operands, array $answers): bool
    {
        $keys = $operands[0] ?? null;
        $minimum = $operands[1] ?? null;

        if (! is_array($keys) || ! is_int($minimum) || $minimum < 1) {
            return false;
        }

        $count = 0;

        foreach ($keys as $key) {
            if (is_string($key) && ($answers[$key] ?? null) === 'yes') {
                $count++;
            }
        }

        return $count >= $minimum;
    }
}

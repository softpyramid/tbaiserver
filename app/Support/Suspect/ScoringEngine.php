<?php

namespace App\Support\Suspect;

use App\Models\SuspectRuleSet;

/**
 * The provisional prioritisation score: S + E + H, capped at 100. It is not
 * a probability. unknown, not_disclosed and "not asked" give 0 points, are
 * never treated as no, and are listed in unknown_items.
 *
 * @phpstan-type ScoreResult array{
 *     symptom: int,
 *     exposure: int,
 *     history: int,
 *     raw: int,
 *     score: int,
 *     unknown_items: list<string>,
 *     not_asked_items: list<string>,
 *     reason_codes: list<string>,
 *     matched_items: list<string>
 * }
 */
class ScoringEngine
{
    public const ENGINE_VERSION = '1.0.0';

    public const MAX_SCORE = 100;

    /**
     * Answers that count as unknown.
     *
     * @var list<string>
     */
    public const UNKNOWN_VALUES = ['unknown', 'not_disclosed'];

    /**
     * Score the answers with this rule set. When $final is true, a scored
     * question that applies but was never answered counts as unknown
     * ("not asked"); while the screening is in progress it does not.
     *
     * @param  array<string, string>  $answers
     * @return ScoreResult
     */
    public function score(SuspectRuleSet $ruleSet, array $answers, bool $final = false): array
    {
        $components = ['S' => 0, 'E' => 0, 'H' => 0];
        $reasons = [];
        $matched = [];

        foreach ($ruleSet->scoreItems as $item) {
            if (! Condition::holds($item->condition, $answers)) {
                continue;
            }

            $matched[] = $item->key;

            if ($item->points <= 0 || ! array_key_exists($item->component, $components)) {
                continue;
            }

            $components[$item->component] += $item->points;
            $reasons[] = strtoupper($item->key);
        }

        [$unknown, $notAsked] = $this->unknowns($ruleSet, $answers, $final);

        foreach ($unknown as $key) {
            $reasons[] = 'UNKNOWN_'.strtoupper($key);
        }

        $raw = $components['S'] + $components['E'] + $components['H'];

        return [
            'symptom' => $components['S'],
            'exposure' => $components['E'],
            'history' => $components['H'],
            'raw' => $raw,
            'score' => min(self::MAX_SCORE, $raw),
            'unknown_items' => $unknown,
            'not_asked_items' => $notAsked,
            'reason_codes' => $reasons,
            'matched_items' => $matched,
        ];
    }

    /**
     * Question keys any score item reads.
     *
     * @return list<string>
     */
    public function scoredKeys(SuspectRuleSet $ruleSet): array
    {
        $keys = [];

        foreach ($ruleSet->scoreItems as $item) {
            foreach (Condition::keys($item->condition) as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{0: list<string>, 1: list<string>}
     */
    private function unknowns(SuspectRuleSet $ruleSet, array $answers, bool $final): array
    {
        $scored = array_flip($this->scoredKeys($ruleSet));
        $unknown = [];
        $notAsked = [];

        foreach ($ruleSet->questions as $question) {
            if ($question->isProfile() || ! isset($scored[$question->key])) {
                continue;
            }

            $value = $answers[$question->key] ?? null;

            if ($value !== null && in_array($value, self::UNKNOWN_VALUES, true)) {
                $unknown[] = $question->key;

                continue;
            }

            if ($value === null && $final && NextQuestions::applies($question, $answers)) {
                $unknown[] = $question->key;
                $notAsked[] = $question->key;
            }
        }

        return [$unknown, $notAsked];
    }
}

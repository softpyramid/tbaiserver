<?php

namespace App\Support\Suspect;

use App\Models\SuspectQuestion;
use App\Models\SuspectRuleSet;

/**
 * Adaptive questioning. A question applies when it has no ask_when or its
 * ask_when holds. Next questions are the applicable, unanswered questions in
 * sort order. An answer whose question stops applying (cough changed to no)
 * is dropped, so a never-asked question has no answer row.
 */
class NextQuestions
{
    /**
     * @param  array<string, string>  $answers
     */
    public static function applies(SuspectQuestion $question, array $answers): bool
    {
        return $question->ask_when === null || Condition::holds($question->ask_when, $answers);
    }

    /**
     * @param  array<string, string>  $answers
     * @return list<SuspectQuestion>
     */
    public function pending(SuspectRuleSet $ruleSet, array $answers): array
    {
        $pending = [];

        foreach ($ruleSet->questions as $question) {
            if ($question->isProfile() || array_key_exists($question->key, $answers)) {
                continue;
            }

            if (self::applies($question, $answers)) {
                $pending[] = $question;
            }
        }

        return $pending;
    }

    /**
     * Applicable, answerable questions: answered ones plus pending ones.
     *
     * @param  array<string, string>  $answers
     */
    public function applicableCount(SuspectRuleSet $ruleSet, array $answers): int
    {
        $count = 0;

        foreach ($ruleSet->questions as $question) {
            if (! $question->isProfile() && self::applies($question, $answers)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Drop answers whose question no longer applies, until nothing changes
     * (a dropped answer can make a further question stop applying).
     *
     * @param  array<string, string>  $answers
     * @return array{0: array<string, string>, 1: list<string>}
     */
    public function prune(SuspectRuleSet $ruleSet, array $answers): array
    {
        $removed = [];

        do {
            $changed = false;

            foreach ($ruleSet->questions as $question) {
                if (! array_key_exists($question->key, $answers) || self::applies($question, $answers)) {
                    continue;
                }

                unset($answers[$question->key]);
                $removed[] = $question->key;
                $changed = true;
            }
        } while ($changed);

        return [$answers, $removed];
    }
}

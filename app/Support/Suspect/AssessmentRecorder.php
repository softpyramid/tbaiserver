<?php

namespace App\Support\Suspect;

use App\Models\Facility;
use App\Models\SuspectAnswer;
use App\Models\SuspectAssessment;
use App\Models\SuspectAssessmentFlag;
use App\Models\SuspectRuleSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts, answers and completes a suspect screening. Answers are stored
 * exactly as given. Results keep the rule set and engine version they were
 * scored with and are never recomputed with a newer set.
 *
 * @phpstan-import-type Evaluation from Assessor
 */
class AssessmentRecorder
{
    public function __construct(
        private readonly Assessor $assessor,
        private readonly NextQuestions $nextQuestions,
    ) {}

    public function start(User $user, Facility $facility, SuspectRuleSet $ruleSet, string $ageBand, string $sex): SuspectAssessment
    {
        return SuspectAssessment::query()->create([
            'facility_id' => $facility->id,
            'city_id' => $facility->city_id,
            'user_id' => $user->id,
            'worker_role' => $user->teamRole($facility->team)?->value,
            'age_band' => $ageBand,
            'sex' => $sex,
            'rule_set_id' => $ruleSet->id,
            'engine_version' => ScoringEngine::ENGINE_VERSION,
            'status' => SuspectAssessment::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Store answers and drop any answer whose question no longer applies.
     *
     * @param  array<string, string>  $given
     * @return list<string> Question keys whose answers were removed or not stored.
     */
    public function answer(SuspectAssessment $assessment, array $given): array
    {
        $ruleSet = $assessment->ruleSet;
        $before = $assessment->answerMap();
        [$after, $removed] = $this->nextQuestions->prune($ruleSet, [...$before, ...$given]);

        DB::transaction(function () use ($assessment, $given, $after, $removed, $before): void {
            if ($removed !== []) {
                $assessment->answers()->whereIn('question_key', $removed)->delete();
            }

            foreach ($given as $key => $value) {
                if (! array_key_exists($key, $after)) {
                    continue;
                }

                if (($before[$key] ?? null) === $value) {
                    continue;
                }

                SuspectAnswer::query()->updateOrCreate(
                    ['assessment_id' => $assessment->id, 'question_key' => $key],
                    ['value' => $value, 'answered_at' => now()],
                );
            }

            $assessment->touch();
        });

        return array_values(array_unique($removed));
    }

    /**
     * Current evaluation of the stored answers, for partial responses.
     *
     * @return Evaluation
     */
    public function evaluate(SuspectAssessment $assessment, bool $final = false): array
    {
        return $this->assessor->evaluate($assessment->ruleSet, $assessment->answerMap(), $final);
    }

    /**
     * Finalise. Unanswered questions stay "not asked" (no row).
     *
     * @return Evaluation
     */
    public function complete(SuspectAssessment $assessment): array
    {
        $evaluation = $this->evaluate($assessment, true);
        $scores = $evaluation['scores'];

        DB::transaction(function () use ($assessment, $evaluation, $scores): void {
            $assessment->forceFill([
                'status' => SuspectAssessment::STATUS_COMPLETED,
                'symptom' => $scores['symptom'],
                'exposure' => $scores['exposure'],
                'history' => $scores['history'],
                'raw_score' => $scores['raw'],
                'score' => $scores['score'],
                'priority' => $evaluation['priority'],
                'refer_for_testing' => $evaluation['refer_for_testing'],
                'recommended_next_action' => $evaluation['recommended_next_action'],
                'unknown_count' => count($scores['unknown_items']),
                'unknown_items' => $scores['unknown_items'],
                'reason_codes' => $evaluation['reason_codes'],
                'criterion_status' => $evaluation['clinical_rule_status']['status'],
                'completed_at' => now(),
            ])->save();

            $assessment->flags()->delete();

            foreach ($evaluation['met_rules'] as $rule) {
                $assessment->flags()->create([
                    'type' => SuspectAssessmentFlag::TYPE_CLINICAL_RULE,
                    'code' => $rule->reason_code,
                    'reason' => $rule->label,
                ]);
            }

            foreach ($evaluation['risk_patterns'] as $pattern) {
                $assessment->flags()->create([
                    'type' => SuspectAssessmentFlag::TYPE_RISK_PATTERN,
                    'code' => $pattern['code'],
                    'reason' => $pattern['reason'],
                ]);
            }
        });

        return $evaluation;
    }
}

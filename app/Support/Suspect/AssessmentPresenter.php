<?php

namespace App\Support\Suspect;

use App\Models\SuspectAssessment;
use App\Models\SuspectAssessmentFlag;
use App\Models\SuspectClinicalRule;
use App\Models\SuspectQuestion;
use App\Models\SuspectRuleSet;
use App\Support\ResourceResponse;

/**
 * JSON shapes for the suspect screening API and pages. The score is always
 * labelled a provisional prioritisation score and is_probability is false.
 * inProgress() and result() carry the patient name and register number, so
 * callers must check SuspectAccess::canRead() (facility staff or TB-AI admin)
 * before using them.
 *
 * @phpstan-import-type Evaluation from Assessor
 */
class AssessmentPresenter
{
    public function __construct(private readonly NextQuestions $nextQuestions) {}

    public static function scoreLabel(): string
    {
        return ResourceResponse::line('entry.suspect_score_label');
    }

    public static function optionLabel(string $value): string
    {
        $key = 'entry.suspect_option_'.$value;
        $line = ResourceResponse::line($key);

        return $line === $key ? str_replace('_', ' ', $value) : $line;
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleSet(SuspectRuleSet $ruleSet): array
    {
        return [
            'version' => $ruleSet->version,
            'status' => $ruleSet->status->value,
            'engine_version' => $ruleSet->engine_version,
            'is_probability' => false,
            'score_label' => self::scoreLabel(),
            'draft_notice' => $ruleSet->isPublished() ? null : ResourceResponse::line('entry.suspect_draft_banner'),
            'thresholds' => [
                'high' => $ruleSet->high_threshold,
                'medium' => $ruleSet->medium_threshold,
                'low' => $ruleSet->low_threshold,
                'refer' => $ruleSet->refer_threshold,
            ],
            'profile' => [
                'age_band' => $this->options(SuspectAssessment::AGE_BANDS),
                'sex' => $this->options(SuspectAssessment::SEXES),
            ],
            'questions' => $ruleSet->questions
                ->map(fn (SuspectQuestion $question): array => [
                    'key' => $question->key,
                    'section' => $question->section,
                    'prompt' => $question->prompt,
                    'help' => $question->help,
                    'options' => $question->options,
                    'option_labels' => $this->labels($question->options),
                    'ask_when' => $question->ask_when,
                ])->values()->all(),
            'score_items' => $ruleSet->scoreItems
                ->map(fn ($item): array => [
                    'component' => $item->component,
                    'key' => $item->key,
                    'label' => $item->label,
                    'points' => $item->points,
                ])->values()->all(),
        ];
    }

    /**
     * A question as asked, with the reason it is asked when it was branched.
     *
     * @return array<string, mixed>
     */
    public function question(SuspectQuestion $question, SuspectRuleSet $ruleSet): array
    {
        return [
            'key' => $question->key,
            'section' => $question->section,
            'section_label' => ResourceResponse::line('entry.suspect_section_'.$question->section),
            'prompt' => $question->prompt,
            'help' => $question->help,
            'options' => $question->options,
            'option_labels' => $this->labels($question->options),
            'why' => $this->why($question, $ruleSet),
        ];
    }

    /**
     * Response while answering: next questions, partial scores, and any
     * clinical rule or alert as soon as it is met.
     *
     * @param  list<string>  $removed
     * @param  Evaluation  $evaluation
     * @return array<string, mixed>
     */
    public function inProgress(SuspectAssessment $assessment, array $removed, array $evaluation): array
    {
        $ruleSet = $assessment->ruleSet;
        $answers = $assessment->answerMap();
        $pending = $this->nextQuestions->pending($ruleSet, $answers);
        $scores = $evaluation['scores'];

        return [
            'assessment_id' => $assessment->uuid,
            'status' => $assessment->status,
            'engine_version' => $assessment->engine_version,
            'rule_set_version' => $ruleSet->version,
            'rule_set_status' => $ruleSet->status->value,
            'score_label' => self::scoreLabel(),
            'patient_name' => $assessment->patient_name,
            'register_number' => $assessment->register_number,
            'age_band' => $assessment->age_band,
            'sex' => $assessment->sex,
            'responses' => $this->responses($ruleSet, $answers),
            'next_questions' => array_map(fn (SuspectQuestion $question): array => $this->question($question, $ruleSet), $pending),
            'progress' => [
                'answered' => count($answers),
                'total' => $this->nextQuestions->applicableCount($ruleSet, $answers),
            ],
            'removed_answers' => $removed,
            'scores' => [
                'symptom' => $scores['symptom'],
                'exposure' => $scores['exposure'],
                'history' => $scores['history'],
                'raw' => $scores['raw'],
                'score' => $scores['score'],
                'is_probability' => false,
            ],
            'unknown_items' => $scores['unknown_items'],
            'testing_criterion_met' => $evaluation['testing_criterion_met'],
            'clinical_rule_status' => $evaluation['clinical_rule_status'],
            'alerts' => $evaluation['alerts'],
        ];
    }

    /**
     * The stored result (spec 4.5) plus refer_for_testing. Read from the
     * stored columns and flags, never re-scored.
     *
     * @return array<string, mixed>
     */
    public function result(SuspectAssessment $assessment): array
    {
        $ruleSet = $assessment->ruleSet;
        $answers = $assessment->answerMap();
        $effects = $ruleSet->clinicalRules
            ->mapWithKeys(fn (SuspectClinicalRule $rule): array => [$rule->reason_code => $rule->effect])
            ->all();
        $flags = $assessment->flags()->orderBy('id')->get();
        $ruleFlags = $flags->where('type', SuspectAssessmentFlag::TYPE_CLINICAL_RULE);
        $alerts = $ruleFlags
            ->filter(fn (SuspectAssessmentFlag $flag): bool => ($effects[$flag->code] ?? null) === SuspectClinicalRule::EFFECT_CLINICAL_ALERT)
            ->pluck('code')->values()->all();
        $criterionMet = $ruleFlags
            ->contains(fn (SuspectAssessmentFlag $flag): bool => ($effects[$flag->code] ?? null) === SuspectClinicalRule::EFFECT_REQUIRE_EVALUATION);

        return [
            'assessment_id' => $assessment->uuid,
            'status' => $assessment->status,
            'completed_at' => $assessment->completed_at?->toIso8601String(),
            'engine_version' => $assessment->engine_version,
            'rule_set_version' => $ruleSet->version,
            'rule_set_status' => $ruleSet->status->value,
            'score_label' => self::scoreLabel(),
            'patient_name' => $assessment->patient_name,
            'register_number' => $assessment->register_number,
            'age_band' => $assessment->age_band,
            'sex' => $assessment->sex,
            'responses' => $this->responses($ruleSet, $answers),
            'scores' => [
                'symptom' => $assessment->symptom,
                'exposure' => $assessment->exposure,
                'history' => $assessment->history,
                'raw' => $assessment->raw_score,
                'score' => $assessment->score,
                'is_probability' => false,
            ],
            'unknown_items' => $assessment->unknown_items ?? [],
            'unknown_count' => $assessment->unknown_count,
            'clinical_rule_status' => [
                'status' => $assessment->criterion_status ?? 'not_configured',
                'testing_criterion_met' => $criterionMet,
                'rules' => $ruleFlags->pluck('code')->values()->all(),
            ],
            'alerts' => $alerts,
            'risk_patterns' => $flags->where('type', SuspectAssessmentFlag::TYPE_RISK_PATTERN)
                ->map(fn (SuspectAssessmentFlag $flag): array => ['code' => $flag->code, 'reason' => $flag->reason])
                ->values()->all(),
            'priority' => $assessment->priority,
            'refer_for_testing' => $assessment->refer_for_testing,
            'recommended_next_action' => $assessment->recommended_next_action,
            'reason_codes' => $assessment->reason_codes ?? [],
            'follow_up' => null,
            'epidemiological_context' => null,
        ];
    }

    /**
     * Stored answers in question order, as given.
     *
     * @param  array<string, string>  $answers
     * @return list<array{key: string, value: string}>
     */
    private function responses(SuspectRuleSet $ruleSet, array $answers): array
    {
        $rows = [];

        foreach ($ruleSet->questions as $question) {
            if (array_key_exists($question->key, $answers)) {
                $rows[] = ['key' => $question->key, 'value' => $answers[$question->key]];
            }
        }

        return $rows;
    }

    private function why(SuspectQuestion $question, SuspectRuleSet $ruleSet): ?string
    {
        $pairs = Condition::equalities($question->ask_when);

        if ($pairs === []) {
            return null;
        }

        $parts = [];

        foreach ($pairs as [$key, $value]) {
            $topicKey = 'entry.suspect_topic_'.$key;
            $topic = ResourceResponse::line($topicKey);

            if ($topic === $topicKey) {
                $source = $ruleSet->questions->firstWhere('key', $key);
                $topic = $source instanceof SuspectQuestion ? $source->prompt : str_replace('_', ' ', $key);
            }

            $parts[] = self::line('entry.suspect_why_part', ['topic' => $topic, 'answer' => mb_strtolower(self::optionLabel($value))]);
        }

        return self::line('entry.suspect_why', ['reason' => implode(', ', $parts)]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function line(string $key, array $replace): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    private function options(array $values): array
    {
        return array_map(fn (string $value): array => ['value' => $value, 'label' => self::optionLabel($value)], $values);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function labels(array $values): array
    {
        $labels = [];

        foreach ($values as $value) {
            $labels[$value] = self::optionLabel($value);
        }

        return $labels;
    }
}

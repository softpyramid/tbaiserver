<?php

namespace App\Support\Suspect;

use App\Models\SuspectClinicalRule;
use App\Models\SuspectRuleSet;
use App\Support\ResourceResponse;

/**
 * Scores answers, applies the clinical rules and risk patterns, and decides
 * priority, referral and the next action. The score never overrules a
 * clinical rule: a met rule always refers and is never low priority.
 *
 * @phpstan-import-type ScoreResult from ScoringEngine
 *
 * @phpstan-type Evaluation array{
 *     scores: ScoreResult,
 *     met_rules: list<SuspectClinicalRule>,
 *     testing_criterion_met: bool,
 *     alerts: list<string>,
 *     clinical_rule_status: array{status: string, testing_criterion_met: bool, rules: list<string>},
 *     risk_patterns: list<array{code: string, reason: string}>,
 *     priority: string,
 *     refer_for_testing: bool,
 *     recommended_next_action: string,
 *     reason_codes: list<string>
 * }
 */
class Assessor
{
    public function __construct(private readonly ScoringEngine $engine) {}

    /**
     * @param  array<string, string>  $answers
     * @return Evaluation
     */
    public function evaluate(SuspectRuleSet $ruleSet, array $answers, bool $final = false): array
    {
        $scores = $this->engine->score($ruleSet, $answers, $final);
        $met = [];
        $criterionConfigured = false;

        foreach ($ruleSet->clinicalRules as $rule) {
            if ($rule->isTestingCriterion() && $rule->configured && $rule->condition !== null) {
                $criterionConfigured = true;
            }

            if ($rule->configured && $rule->condition !== null && Condition::holds($rule->condition, $answers)) {
                $met[] = $rule;
            }
        }

        $criterionMet = false;
        $alerts = [];
        $ruleCodes = [];
        $floor = false;

        foreach ($met as $rule) {
            $ruleCodes[] = $rule->reason_code;
            $criterionMet = $criterionMet || $rule->isTestingCriterion();
            $floor = $floor || $rule->priority_floor === SuspectClinicalRule::FLOOR_EVALUATION_REQUIRED;

            if ($rule->isAlert()) {
                $alerts[] = $rule->reason_code;
            }
        }

        $patterns = [];

        foreach ($ruleSet->riskPatterns as $pattern) {
            if ($pattern->active && Condition::holds($pattern->condition, $answers)) {
                $patterns[] = ['code' => $pattern->code, 'reason' => $pattern->reason];
            }
        }

        $refer = $scores['score'] >= $ruleSet->refer_threshold || $met !== [];

        return [
            'scores' => $scores,
            'met_rules' => $met,
            'testing_criterion_met' => $criterionMet,
            'alerts' => $alerts,
            'clinical_rule_status' => [
                'status' => $criterionConfigured ? 'configured' : 'not_configured',
                'testing_criterion_met' => $criterionMet,
                'rules' => $ruleCodes,
            ],
            'risk_patterns' => $patterns,
            'priority' => Priority::resolve($scores['score'], $ruleSet, $floor || $met !== []),
            'refer_for_testing' => $refer,
            'recommended_next_action' => match (true) {
                $met !== [] => $met[0]->next_action,
                $refer => ResourceResponse::line('entry.suspect_action_refer'),
                default => ResourceResponse::line('entry.suspect_action_none'),
            },
            'reason_codes' => array_values(array_unique([...$scores['reason_codes'], ...$ruleCodes])),
        ];
    }
}

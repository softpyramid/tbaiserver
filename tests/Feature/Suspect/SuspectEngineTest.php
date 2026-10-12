<?php

use App\Enums\SuspectRuleSetStatus;
use App\Enums\TeamRole;
use App\Models\SuspectAnswer;
use App\Models\SuspectAssessment;
use App\Models\SuspectClinicalRule;
use App\Models\SuspectRuleSet;
use App\Support\Suspect\AssessmentPresenter;
use App\Support\Suspect\AssessmentRecorder;
use App\Support\Suspect\Assessor;
use App\Support\Suspect\NextQuestions;
use App\Support\Suspect\Priority;
use App\Support\Suspect\RuleSetWorkflow;
use App\Support\Suspect\ScoringEngine;

/**
 * Every scored question answered no, so only the listed answers count.
 *
 * @param  array<string, string>  $answers
 * @return array<string, string>
 */
function suspectAnswersWithRestNo(array $answers): array
{
    $no = [
        'cough_current' => 'no', 'sputum' => 'no', 'hemoptysis' => 'no', 'fever' => 'no',
        'night_sweats' => 'no', 'weight_loss' => 'no', 'appetite_loss' => 'no', 'fatigue' => 'no',
        'close_contact' => 'no', 'previous_tb' => 'no', 'hiv' => 'no', 'diabetes' => 'no', 'undernutrition' => 'no',
    ];

    return [...$no, ...$answers];
}

function configureCoughCriterion(SuspectRuleSet $ruleSet): void
{
    $item = $ruleSet->scoreItems->firstWhere('key', 'cough_gte_2_weeks');

    $ruleSet->clinicalRules
        ->firstWhere('code', 'TESTING_CRITERION_MET')
        ->update([
            'condition' => $item->condition,
            'criterion_reference' => 'Test reference',
            'configured' => true,
        ]);

    $ruleSet->load('clinicalRules');
}

test('T1 cough 2 weeks or more with sputum and night sweats scores S 31', function () {
    $ruleSet = suspectDraftRuleSet();

    $result = app(ScoringEngine::class)->score($ruleSet, suspectAnswersWithRestNo([
        'cough_current' => 'yes',
        'cough_duration' => 'gte_2_weeks',
        'sputum' => 'yes',
        'night_sweats' => 'yes',
    ]), true);

    expect($result['symptom'])->toBe(31)
        ->and($result['exposure'])->toBe(0)
        ->and($result['history'])->toBe(0)
        ->and($result['raw'])->toBe(31)
        ->and($result['score'])->toBe(31)
        ->and($result['unknown_items'])->toBe([])
        ->and($result['reason_codes'])->toBe(['COUGH_CURRENT', 'COUGH_GTE_2_WEEKS', 'SPUTUM', 'NIGHT_SWEATS']);
});

test('T2 close and household contact score E 20', function () {
    $result = app(ScoringEngine::class)->score(suspectDraftRuleSet(), [
        'close_contact' => 'yes',
        'household_contact' => 'yes',
    ]);

    expect($result['exposure'])->toBe(20)->and($result['score'])->toBe(20);
});

test('T3 unknown close contact scores E 0, is listed, and is stored as unknown', function () {
    $ruleSet = suspectDraftRuleSet();

    $result = app(ScoringEngine::class)->score($ruleSet, [
        'close_contact' => 'unknown',
        'household_contact' => 'yes',
    ]);

    expect($result['exposure'])->toBe(0)
        ->and($result['unknown_items'])->toBe(['close_contact'])
        ->and($result['reason_codes'])->toContain('UNKNOWN_CLOSE_CONTACT');

    [$user, $facility] = suspectStaff();
    $recorder = app(AssessmentRecorder::class);
    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '25_44', 'female');
    $recorder->answer($assessment, ['close_contact' => 'unknown']);
    $recorder->complete($assessment);

    expect(SuspectAnswer::query()->where('assessment_id', $assessment->id)->pluck('value', 'question_key')->all())
        ->toBe(['close_contact' => 'unknown'])
        ->and($assessment->refresh()->exposure)->toBe(0)
        ->and($assessment->unknown_items)->toContain('close_contact')
        ->and($assessment->reason_codes)->toContain('UNKNOWN_CLOSE_CONTACT');
});

test('T4 every scored item yes gives raw 102 and score 100', function () {
    $result = app(ScoringEngine::class)->score(suspectDraftRuleSet(), [
        'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'sputum' => 'yes',
        'fever' => 'yes', 'fever_gte_2_weeks' => 'yes', 'night_sweats' => 'yes', 'weight_loss' => 'yes',
        'appetite_loss' => 'yes', 'fatigue' => 'yes', 'close_contact' => 'yes', 'household_contact' => 'yes',
        'previous_tb' => 'yes', 'hiv' => 'yes', 'diabetes' => 'yes', 'undernutrition' => 'yes',
    ]);

    expect($result['symptom'])->toBe(56)
        ->and($result['exposure'])->toBe(20)
        ->and($result['history'])->toBe(26)
        ->and($result['raw'])->toBe(102)
        ->and($result['score'])->toBe(100);
});

test('T5 hemoptysis alone scores 0 but raises the alert, needs evaluation and refers', function () {
    $evaluation = app(Assessor::class)->evaluate(suspectDraftRuleSet(), suspectAnswersWithRestNo([
        'hemoptysis' => 'yes',
    ]), true);

    expect($evaluation['scores']['score'])->toBe(0)
        ->and($evaluation['alerts'])->toBe(['HEMOPTYSIS_CLINICAL_REVIEW'])
        ->and($evaluation['priority'])->toBe('evaluation_required')
        ->and($evaluation['refer_for_testing'])->toBeTrue()
        ->and($evaluation['recommended_next_action'])->toBe('Clinical review per escalation protocol')
        ->and($evaluation['reason_codes'])->toContain('HEMOPTYSIS_CLINICAL_REVIEW');
});

test('T6 cough under 2 weeks scores S 8 without the prolonged points', function () {
    $result = app(ScoringEngine::class)->score(suspectDraftRuleSet(), [
        'cough_current' => 'yes',
        'cough_duration' => 'lt_2_weeks',
    ]);

    expect($result['symptom'])->toBe(8)
        ->and($result['reason_codes'])->not->toContain('COUGH_GTE_2_WEEKS');
});

test('T7 fever that has not lasted 2 weeks scores 0', function () {
    $result = app(ScoringEngine::class)->score(suspectDraftRuleSet(), [
        'fever' => 'yes',
        'fever_gte_2_weeks' => 'no',
    ]);

    expect($result['symptom'])->toBe(0);
});

test('T8 HIV not disclosed scores 0 and is stored as not_disclosed; diabetes gives H 4', function () {
    $ruleSet = suspectDraftRuleSet();
    $result = app(ScoringEngine::class)->score($ruleSet, ['hiv' => 'not_disclosed', 'diabetes' => 'yes']);

    expect($result['history'])->toBe(4)
        ->and($result['unknown_items'])->toBe(['hiv'])
        ->and($result['reason_codes'])->toContain('UNKNOWN_HIV');

    [$user, $facility] = suspectStaff();
    $recorder = app(AssessmentRecorder::class);
    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '45_64', 'male');
    $recorder->answer($assessment, ['hiv' => 'not_disclosed', 'diabetes' => 'yes']);

    expect(SuspectAnswer::query()->where('assessment_id', $assessment->id)->where('question_key', 'hiv')->value('value'))
        ->toBe('not_disclosed');
});

test('T9 a low score with the configured testing criterion met needs evaluation and refers', function () {
    $ruleSet = suspectDraftRuleSet();
    configureCoughCriterion($ruleSet);

    $evaluation = app(Assessor::class)->evaluate($ruleSet, suspectAnswersWithRestNo([
        'cough_current' => 'yes',
        'cough_duration' => 'gte_2_weeks',
    ]), true);

    expect($evaluation['scores']['score'])->toBe(18)
        ->and(Priority::resolve(18, $ruleSet, false))->toBe('low')
        ->and($evaluation['priority'])->toBe('evaluation_required')
        ->and($evaluation['testing_criterion_met'])->toBeTrue()
        ->and($evaluation['clinical_rule_status'])->toBe([
            'status' => 'configured',
            'testing_criterion_met' => true,
            'rules' => ['TESTING_CRITERION_MET'],
        ])
        ->and($evaluation['reason_codes'])->toContain('TESTING_CRITERION_MET')
        ->and($evaluation['refer_for_testing'])->toBeTrue()
        ->and($evaluation['recommended_next_action'])->toBe('Refer for diagnostic evaluation');
});

test('T10 a clone with new weights leaves an old assessment and its rule set unchanged', function () {
    $ruleSet = suspectDraftRuleSet();
    [$user, $facility] = suspectStaff();
    $recorder = app(AssessmentRecorder::class);

    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '25_44', 'female');
    $recorder->answer($assessment, suspectAnswersWithRestNo(['cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks']));
    $recorder->complete($assessment);
    $before = app(AssessmentPresenter::class)->result($assessment->refresh());

    $admin = suspectAdmin();
    $clone = app(RuleSetWorkflow::class)->cloneToDraft($ruleSet, $admin);
    $clone->scoreItems()->where('key', 'cough_current')->first()->update(['points' => 30]);
    $ruleSet->scoreItems()->where('key', 'cough_gte_2_weeks')->first()->update(['points' => 40]);

    $assessment = SuspectAssessment::query()->findOrFail($assessment->id);
    $after = app(AssessmentPresenter::class)->result($assessment);

    expect($assessment->rule_set_id)->toBe($ruleSet->id)
        ->and($assessment->engine_version)->toBe(ScoringEngine::ENGINE_VERSION)
        ->and($assessment->score)->toBe(18)
        ->and($after['scores'])->toBe($before['scores'])
        ->and($after['rule_set_version'])->toBe(SuspectRuleSet::query()->find($ruleSet->id)->version)
        ->and($clone->id)->not->toBe($ruleSet->id);
});

test('refer_for_testing switches on exactly at the refer threshold', function () {
    $ruleSet = suspectDraftRuleSet();
    $ruleSet->update(['refer_threshold' => 31]);
    $assessor = app(Assessor::class);

    $at = $assessor->evaluate($ruleSet, suspectAnswersWithRestNo([
        'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'sputum' => 'yes', 'night_sweats' => 'yes',
    ]), true);
    $below = $assessor->evaluate($ruleSet, suspectAnswersWithRestNo([
        'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'sputum' => 'yes', 'fatigue' => 'yes', 'appetite_loss' => 'yes',
    ]), true);

    expect($at['scores']['score'])->toBe(31)
        ->and($at['refer_for_testing'])->toBeTrue()
        ->and($at['recommended_next_action'])->toBe('Refer for diagnostic evaluation')
        ->and($below['scores']['score'])->toBe(30)
        ->and($below['refer_for_testing'])->toBeFalse()
        ->and($below['priority'])->toBe('medium')
        ->and($below['recommended_next_action'])->toBe('No referral indicated by the provisional score. Use clinical judgement.');
});

test('a placeholder testing criterion is reported as not configured and never evaluated', function () {
    $ruleSet = suspectDraftRuleSet();
    $criterion = $ruleSet->clinicalRules->firstWhere('code', 'TESTING_CRITERION_MET');

    expect($criterion->configured)->toBeFalse()
        ->and($criterion->condition)->toBeNull()
        ->and($criterion->effect)->toBe(SuspectClinicalRule::EFFECT_REQUIRE_EVALUATION);

    $evaluation = app(Assessor::class)->evaluate($ruleSet, ['cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks'], true);

    expect($evaluation['clinical_rule_status']['status'])->toBe('not_configured')
        ->and($evaluation['testing_criterion_met'])->toBeFalse()
        ->and($evaluation['priority'])->toBe('low');
});

test('adaptive questions skip branches that do not apply and drop answers that stop applying', function () {
    $ruleSet = suspectDraftRuleSet();
    $next = app(NextQuestions::class);
    $keys = fn (array $answers): array => array_map(fn ($question) => $question->key, $next->pending($ruleSet, $answers));

    expect($keys([]))->not->toContain('cough_duration')
        ->and($keys([])[0])->toBe('cough_current')
        ->and($keys(['cough_current' => 'no']))->not->toContain('cough_duration')
        ->and($keys(['cough_current' => 'yes'])[0])->toBe('cough_duration')
        ->and($keys(['close_contact' => 'unknown']))->not->toContain('household_contact')
        ->and($keys(['close_contact' => 'yes']))->toContain('household_contact');

    [$user, $facility] = suspectStaff(TeamRole::Owner);
    $recorder = app(AssessmentRecorder::class);
    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '25_44', 'female');

    $recorder->answer($assessment, ['cough_current' => 'no']);
    expect($assessment->answers()->where('question_key', 'cough_duration')->exists())->toBeFalse();

    $recorder->answer($assessment, ['cough_current' => 'yes']);
    $recorder->answer($assessment, ['cough_duration' => 'gte_2_weeks']);
    expect($assessment->answers()->where('question_key', 'cough_duration')->value('value'))->toBe('gte_2_weeks');

    $removed = $recorder->answer($assessment, ['cough_current' => 'no']);

    expect($removed)->toBe(['cough_duration'])
        ->and($assessment->answers()->where('question_key', 'cough_duration')->exists())->toBeFalse()
        ->and($assessment->answerMap())->toBe(['cough_current' => 'no']);
});

test('only active risk patterns are reported', function () {
    $ruleSet = suspectDraftRuleSet();
    $answers = ['cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'close_contact' => 'yes'];

    expect(app(Assessor::class)->evaluate($ruleSet, $answers)['risk_patterns'])->toBe([]);

    $ruleSet->riskPatterns->firstWhere('code', 'PERSISTENT_COUGH_WITH_TB_CONTACT')->update(['active' => true]);
    $ruleSet->load('riskPatterns');

    expect(app(Assessor::class)->evaluate($ruleSet, $answers)['risk_patterns'])->toBe([[
        'code' => 'PERSISTENT_COUGH_WITH_TB_CONTACT',
        'reason' => 'Cough 2 weeks or more and known TB contact',
    ]]);
});

test('an unanswered question that applies counts as not asked when the screening completes', function () {
    $ruleSet = suspectDraftRuleSet();
    $result = app(ScoringEngine::class)->score($ruleSet, ['cough_current' => 'yes'], true);

    expect($result['not_asked_items'])->toContain('cough_duration')
        ->and($result['unknown_items'])->toContain('cough_duration')
        ->and($result['not_asked_items'])->not->toContain('household_contact');
});

test('the seeder is idempotent and an approved set cannot be edited', function () {
    $ruleSet = suspectDraftRuleSet();
    suspectDraftRuleSet();

    expect(SuspectRuleSet::query()->count())->toBe(2)
        ->and($ruleSet->questions)->toHaveCount(19)
        ->and($ruleSet->scoreItems)->toHaveCount(14)
        ->and($ruleSet->riskPatterns->where('active', true))->toHaveCount(0);

    $ruleSet->forceFill([
        'status' => 'approved',
        'approved_by' => suspectAdmin()->id,
        'approved_at' => now(),
        'clinical_signoff_reference' => 'Ref',
    ])->save();

    expect(fn () => $ruleSet->scoreItems->first()->update(['points' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $ruleSet->refresh()->update(['high_threshold' => 10]))->toThrow(LogicException::class);
});

test('tbai:suspect-seed adds both drafts once and never duplicates them', function () {
    $this->artisan('tbai:suspect-seed')
        ->expectsOutput('Draft suspect screening rule sets 2026.10-draft.1 and 2026.10-draft.2 are present.')
        ->assertSuccessful();
    $this->artisan('tbai:suspect-seed')->assertSuccessful();

    expect(SuspectRuleSet::query()->count())->toBe(2)
        ->and(SuspectRuleSet::query()->orderBy('id')->pluck('version')->all())->toBe(['2026.10-draft.1', '2026.10-draft.2'])
        ->and(SuspectRuleSet::query()->pluck('status')->unique()->all())->toBe([SuspectRuleSetStatus::Draft]);
});

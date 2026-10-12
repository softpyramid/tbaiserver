<?php

use App\Enums\SuspectRuleSetStatus;
use App\Models\SuspectRuleSet;
use App\Support\Suspect\Assessor;
use App\Support\Suspect\Priority;
use App\Support\Suspect\RuleSetResolver;
use Database\Seeders\SuspectRuleSetSeeder;

/**
 * Every scored question answered no, so only the listed answers count.
 *
 * @param  array<string, string>  $answers
 * @return array<string, string>
 */
function draftTwoAnswers(array $answers): array
{
    $no = [
        'cough_current' => 'no', 'sputum' => 'no', 'hemoptysis' => 'no', 'fever' => 'no',
        'night_sweats' => 'no', 'weight_loss' => 'no', 'appetite_loss' => 'no', 'fatigue' => 'no',
        'close_contact' => 'no', 'previous_tb' => 'no', 'hiv' => 'no', 'diabetes' => 'no', 'undernutrition' => 'no',
    ];

    return [...$no, ...$answers];
}

test('draft.2 is seeded with thresholds 70/40/20/70 and the new weights, and draft.1 is unchanged', function () {
    $one = suspectDraftRuleSet();
    $two = suspectDraftTwoRuleSet();
    $points = fn (SuspectRuleSet $ruleSet): array => $ruleSet->scoreItems->pluck('points', 'key')->all();

    expect($two->status)->toBe(SuspectRuleSetStatus::Draft)
        ->and($two->id)->toBeGreaterThan($one->id)
        ->and([$two->high_threshold, $two->medium_threshold, $two->low_threshold, $two->refer_threshold])->toBe([70, 40, 20, 70])
        ->and($two->scoreItems->firstWhere('key', 'fever_gte_2_weeks')->label)->toBe('Prolonged fever (2 weeks or more)')
        ->and($points($two)['fever_gte_2_weeks'])->toBe(15)
        ->and($points($two)['previous_tb'])->toBe(10)
        ->and($points($two)['diabetes'])->toBe(8)
        ->and(array_sum($points($two)))->toBe(115)
        ->and($two->questions->pluck('key')->all())->toBe($one->questions->pluck('key')->all())
        ->and($two->clinicalRules->pluck('code')->all())->toBe(['HEMOPTYSIS_CLINICAL_REVIEW', 'TESTING_CRITERION_MET'])
        ->and($two->riskPatterns->pluck('code')->all())->toBe($one->riskPatterns->pluck('code')->all())
        ->and($two->riskPatterns->where('active', true))->toHaveCount(0);

    expect([$one->high_threshold, $one->medium_threshold, $one->low_threshold, $one->refer_threshold])->toBe([40, 20, 0, 40])
        ->and($one->scoreItems->firstWhere('key', 'fever_gte_2_weeks')->label)->toBe('Fever lasting 2 weeks or more')
        ->and($points($one))->toBe([
            'cough_current' => 8, 'cough_gte_2_weeks' => 10, 'sputum' => 5, 'fever_gte_2_weeks' => 8,
            'night_sweats' => 8, 'weight_loss' => 10, 'appetite_loss' => 4, 'fatigue' => 3,
            'close_contact' => 15, 'household_contact' => 5,
            'previous_tb' => 8, 'hiv' => 10, 'diabetes' => 4, 'undernutrition' => 4,
        ]);
});

test('seeding twice creates no duplicates and never overwrites an edited draft', function () {
    $two = suspectDraftTwoRuleSet();
    $two->update(['low_threshold' => 15]);
    $two->scoreItems->firstWhere('key', 'diabetes')->update(['points' => 6]);

    (new SuspectRuleSetSeeder)->run();
    (new SuspectRuleSetSeeder)->run();

    expect(SuspectRuleSet::query()->count())->toBe(2)
        ->and(SuspectRuleSet::query()->where('version', SuspectRuleSetSeeder::VERSION_2)->count())->toBe(1)
        ->and($two->refresh()->low_threshold)->toBe(15)
        ->and($two->scoreItems()->where('key', 'diabetes')->value('points'))->toBe(6)
        ->and($two->scoreItems()->count())->toBe(14);
});

test('an existing draft.1 gets draft.2 added after it', function () {
    suspectDraftRuleSet();
    SuspectRuleSet::query()->where('version', SuspectRuleSetSeeder::VERSION_2)->sole()->delete();

    expect(SuspectRuleSet::query()->count())->toBe(1);

    (new SuspectRuleSetSeeder)->run();

    expect(SuspectRuleSet::query()->orderBy('id')->pluck('version')->all())->toBe(['2026.10-draft.1', '2026.10-draft.2'])
        ->and(app(RuleSetResolver::class)->current()?->version)->toBe('2026.10-draft.2');
});

test('the resolver serves draft.2 as the newest draft', function () {
    suspectDraftRuleSet();

    expect(app(RuleSetResolver::class)->current()?->version)->toBe('2026.10-draft.2');
});

test('draft.2 bands scores at the threshold boundaries', function (int $score, string $band) {
    expect(Priority::resolve($score, suspectDraftTwoRuleSet(), false))->toBe($band);
})->with([
    [0, 'none'],
    [19, 'none'],
    [20, 'low'],
    [39, 'low'],
    [40, 'medium'],
    [69, 'medium'],
    [70, 'high'],
    [100, 'high'],
]);

test('the clinical floor lifts none, low and medium but keeps high', function (int $score, string $priority) {
    expect(Priority::resolve($score, suspectDraftTwoRuleSet(), true))->toBe($priority);
})->with([
    [0, 'evaluation_required'],
    [19, 'evaluation_required'],
    [20, 'evaluation_required'],
    [40, 'evaluation_required'],
    [69, 'evaluation_required'],
    [70, 'high'],
]);

test('every band is listed', function () {
    expect(Priority::all())->toBe(['high', 'medium', 'low', 'none', 'evaluation_required']);
});

test('draft.2 answers score, band and refer at the boundaries', function (array $answers, int $score, string $priority, bool $refer) {
    $evaluation = app(Assessor::class)->evaluate(suspectDraftTwoRuleSet(), draftTwoAnswers($answers), true);

    expect($evaluation['scores']['score'])->toBe($score)
        ->and($evaluation['priority'])->toBe($priority)
        ->and($evaluation['refer_for_testing'])->toBe($refer)
        ->and($evaluation['alerts'])->toBe([]);
})->with([
    'score 19' => [['cough_current' => 'yes', 'cough_duration' => 'lt_2_weeks', 'night_sweats' => 'yes', 'fatigue' => 'yes'], 19, 'none', false],
    'score 20' => [['close_contact' => 'yes', 'household_contact' => 'yes'], 20, 'low', false],
    'score 39' => [['close_contact' => 'yes', 'household_contact' => 'yes', 'fever' => 'yes', 'fever_gte_2_weeks' => 'yes', 'appetite_loss' => 'yes'], 39, 'low', false],
    'score 40' => [['close_contact' => 'yes', 'household_contact' => 'yes', 'previous_tb' => 'yes', 'previous_tb_treatment' => 'yes', 'hiv' => 'yes'], 40, 'medium', false],
    'score 69' => [['close_contact' => 'yes', 'household_contact' => 'yes', 'previous_tb' => 'yes', 'previous_tb_treatment' => 'yes', 'hiv' => 'yes', 'diabetes' => 'yes', 'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'fatigue' => 'yes'], 69, 'medium', false],
    'score 70' => [['close_contact' => 'yes', 'household_contact' => 'yes', 'previous_tb' => 'yes', 'previous_tb_treatment' => 'yes', 'hiv' => 'yes', 'diabetes' => 'yes', 'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'appetite_loss' => 'yes'], 70, 'high', true],
]);

test('hemoptysis forces refer and evaluation_required under draft.2 even at score 0', function () {
    $evaluation = app(Assessor::class)->evaluate(suspectDraftTwoRuleSet(), draftTwoAnswers(['hemoptysis' => 'yes']), true);

    expect($evaluation['scores']['score'])->toBe(0)
        ->and(Priority::resolve(0, suspectDraftTwoRuleSet(), false))->toBe('none')
        ->and($evaluation['priority'])->toBe('evaluation_required')
        ->and($evaluation['refer_for_testing'])->toBeTrue()
        ->and($evaluation['alerts'])->toBe(['HEMOPTYSIS_CLINICAL_REVIEW'])
        ->and($evaluation['recommended_next_action'])->toBe('Clinical review per escalation protocol');
});

test('the spec cases T1 to T9 under draft.2 weights and thresholds', function (array $answers, int $score, string $priority, bool $refer, bool $criterion = false) {
    $ruleSet = suspectDraftTwoRuleSet();

    if ($criterion) {
        $ruleSet->clinicalRules->firstWhere('code', 'TESTING_CRITERION_MET')->update([
            'condition' => $ruleSet->scoreItems->firstWhere('key', 'cough_gte_2_weeks')->condition,
            'criterion_reference' => 'Test reference',
            'configured' => true,
        ]);
        $ruleSet->load('clinicalRules');
    }

    $evaluation = app(Assessor::class)->evaluate($ruleSet, $answers, true);

    expect($evaluation['scores']['score'])->toBe($score)
        ->and($evaluation['priority'])->toBe($priority)
        ->and($evaluation['refer_for_testing'])->toBe($refer);
})->with([
    'T1' => [draftTwoAnswers(['cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'sputum' => 'yes', 'night_sweats' => 'yes']), 31, 'low', false],
    'T2' => [['close_contact' => 'yes', 'household_contact' => 'yes'], 20, 'low', false],
    'T3' => [['close_contact' => 'unknown', 'household_contact' => 'yes'], 0, 'none', false],
    'T4' => [[
        'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'sputum' => 'yes',
        'fever' => 'yes', 'fever_gte_2_weeks' => 'yes', 'night_sweats' => 'yes', 'weight_loss' => 'yes',
        'appetite_loss' => 'yes', 'fatigue' => 'yes', 'close_contact' => 'yes', 'household_contact' => 'yes',
        'previous_tb' => 'yes', 'hiv' => 'yes', 'diabetes' => 'yes', 'undernutrition' => 'yes',
    ], 100, 'high', true],
    'T5' => [draftTwoAnswers(['hemoptysis' => 'yes']), 0, 'evaluation_required', true],
    'T6' => [['cough_current' => 'yes', 'cough_duration' => 'lt_2_weeks'], 8, 'none', false],
    'T7' => [['fever' => 'yes', 'fever_gte_2_weeks' => 'no'], 0, 'none', false],
    'T8' => [['hiv' => 'not_disclosed', 'diabetes' => 'yes'], 8, 'none', false],
    'T9' => [draftTwoAnswers(['cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks']), 18, 'evaluation_required', true, true],
]);

test('T4 under draft.2 has raw 115 before the cap', function () {
    $evaluation = app(Assessor::class)->evaluate(suspectDraftTwoRuleSet(), [
        'cough_current' => 'yes', 'cough_duration' => 'gte_2_weeks', 'sputum' => 'yes',
        'fever' => 'yes', 'fever_gte_2_weeks' => 'yes', 'night_sweats' => 'yes', 'weight_loss' => 'yes',
        'appetite_loss' => 'yes', 'fatigue' => 'yes', 'close_contact' => 'yes', 'household_contact' => 'yes',
        'previous_tb' => 'yes', 'hiv' => 'yes', 'diabetes' => 'yes', 'undernutrition' => 'yes',
    ], true);

    expect($evaluation['scores']['symptom'])->toBe(63)
        ->and($evaluation['scores']['exposure'])->toBe(20)
        ->and($evaluation['scores']['history'])->toBe(32)
        ->and($evaluation['scores']['raw'])->toBe(115)
        ->and($evaluation['scores']['score'])->toBe(100);
});

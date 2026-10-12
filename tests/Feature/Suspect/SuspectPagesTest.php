<?php

use App\Enums\TeamRole;
use App\Models\SuspectAssessment;
use App\Support\Suspect\AssessmentRecorder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('suspect_screening');
});

test('facility staff see the suspect screening list with the draft notice and nav entry', function () {
    $ruleSet = suspectDraftRuleSet();
    [$user, $facility] = suspectStaff(TeamRole::Member);
    $recorder = app(AssessmentRecorder::class);
    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '25_44', 'female');
    $recorder->answer($assessment, ['hemoptysis' => 'yes']);
    $recorder->complete($assessment);

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/index')
            ->where('teamSlug', $facility->team->slug)
            ->where('unavailable', null)
            ->where('draftNotice', 'Provisional prioritisation score, not a probability. Weights are pending clinical sign-off.')
            ->where('canScreen', true)
            ->where('newUrl', route('suspect.create', $facility->team))
            ->has('rows', 1)
            ->where('rows.0.id', $assessment->uuid)
            ->where('rows.0.patientName', 'Test Patient')
            ->where('rows.0.registerNumber', 'REG-TEST-1')
            ->where('rows.0.ageBand', '25 to 44')
            ->where('rows.0.sex', 'Female')
            ->where('rows.0.score', 0)
            ->where('rows.0.priority', 'evaluation_required')
            ->where('rows.0.refer', true)
            ->where('nav.groups.0.items', fn ($items) => collect($items)->contains('key', 'suspect_screening')));
});

test('the new screening page carries the api endpoints and profile options', function () {
    suspectDraftRuleSet();
    [$user, $facility] = suspectStaff(TeamRole::Owner);

    $this->actingAs($user)
        ->get(route('suspect.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/new')
            ->where('unavailable', null)
            ->where('ruleSetVersion', '2026.10-draft.2')
            ->where('endpoints.start', route('api.suspect.assessments.store'))
            ->where('endpoints.assessments', url('/api/v1/suspect/assessments'))
            ->has('profile.ageBands', 6)
            ->where('profile.ageBands.5', ['value' => 'unknown', 'label' => 'Unknown'])
            ->has('profile.sexes', 4)
            ->where('i18n.entry.suspect_privacy_note', 'Record the patient name and register number only. Do not enter a phone number, CNIC, national ID or MR number.'));
});

test('the result page shows the stored result with alerts, referral and rule set', function () {
    $ruleSet = suspectDraftRuleSet();
    [$user, $facility] = suspectStaff();
    $recorder = app(AssessmentRecorder::class);
    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '15_24', 'male');
    $recorder->answer($assessment, ['hemoptysis' => 'yes', 'close_contact' => 'unknown']);
    $recorder->complete($assessment);

    $this->actingAs($user)
        ->get(route('suspect.show', [$facility->team, $assessment->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/show')
            ->where('result.assessment_id', $assessment->uuid)
            ->where('result.patient_name', 'Test Patient')
            ->where('result.register_number', 'REG-TEST-1')
            ->where('result.score_label', 'Provisional prioritisation score, not a probability')
            ->where('result.scores.is_probability', false)
            ->where('result.scores.score', 0)
            ->where('result.alerts', ['HEMOPTYSIS_CLINICAL_REVIEW'])
            ->where('result.refer_for_testing', true)
            ->where('result.priority', 'evaluation_required')
            ->where('result.recommended_next_action', 'Clinical review per escalation protocol')
            ->where('result.unknown_items', fn ($items) => collect($items)->contains('close_contact'))
            ->where('result.risk_patterns', [])
            ->where('priorityLabel', 'Evaluation required')
            ->where('ruleSet.version', '2026.10-draft.1')
            ->where('ruleSet.status', 'draft')
            ->where('questionLabels.close_contact', 'Have you been in close contact with a person diagnosed with TB?'));
});

test('another facility cannot open a result and viewers cannot open the pages', function () {
    $ruleSet = suspectDraftRuleSet();
    [$user, $facility] = suspectStaff();
    $assessment = app(AssessmentRecorder::class)->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '15_24', 'male');
    [$other, $otherFacility] = suspectStaff();

    $this->actingAs($other)
        ->get(route('suspect.show', [$otherFacility->team, $assessment->uuid]))
        ->assertNotFound();
    $this->actingAs($other)
        ->get(route('suspect.show', [$facility->team, $assessment->uuid]))
        ->assertForbidden();
    $this->actingAs($other)
        ->get(route('suspect.create', $facility->team))
        ->assertForbidden();

    $admin = suspectAdmin();
    $this->actingAs($admin)
        ->get(route('suspect.show', [$facility->team, $assessment->uuid]))
        ->assertOk();
    $this->actingAs($admin)
        ->get(route('suspect.create', $facility->team))
        ->assertForbidden();
});

test('the pages are not found while the flag is off and no nav entry shows', function () {
    config(['tbai.features.suspect_screening' => false]);
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)->get(route('suspect.index', $facility->team))->assertNotFound();
    $this->actingAs($user)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.groups.0.items', fn ($items) => ! collect($items)->contains('key', 'suspect_screening')));
});

test('production with no approved set shows the unavailable text', function () {
    suspectDraftRuleSet();
    [$user, $facility] = suspectStaff();
    app()->instance('env', 'production');

    $this->actingAs($user)
        ->get(route('suspect.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/new')
            ->where('unavailable', 'Suspect screening is not available until a clinically approved rule set is published.'));
});

test('the admin rule set page and lists render with their props', function () {
    $ruleSet = suspectDraftRuleSet();
    $admin = suspectAdmin();
    [$user, $facility] = suspectStaff();
    app(AssessmentRecorder::class)->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '15_24', 'male');

    $this->actingAs($admin)
        ->get(route('admin.suspect-rules.show', $ruleSet))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/rules-show')
            ->where('ruleSet.version', '2026.10-draft.1')
            ->where('ruleSet.status', 'draft')
            ->where('ruleSet.editable', true)
            ->where('ruleSet.thresholds', ['high' => 40, 'medium' => 20, 'low' => 0, 'refer' => 40])
            ->has('questions', 19)
            ->has('scoreItems', 14)
            ->where('scoreItems.1.key', 'cough_gte_2_weeks')
            ->where('scoreItems.1.points', 10)
            ->where('scoreItems.1.condition', 'cough_current is yes and cough_duration is gte_2_weeks')
            ->has('clinicalRules', 2)
            ->where('clinicalRules.0.editable', false)
            ->where('clinicalRules.1.code', 'TESTING_CRITERION_MET')
            ->where('clinicalRules.1.editable', true)
            ->where('clinicalRules.1.configured', false)
            ->has('riskPatterns', 4)
            ->where('riskPatterns.0.active', false)
            ->has('criterionOptions', 14)
            ->where('actions', ['clone' => true, 'submit' => true, 'approve' => false, 'publish' => false, 'retire' => true])
            ->where('nav.groups.1.items', fn ($items) => collect($items)->contains('key', 'suspect_rules')));

    $this->actingAs($admin)
        ->get(route('admin.suspect-rules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->has('rows', 2)
            ->where('rows.0.cells.version', '2026.10-draft.2')
            ->where('rows.1.cells.version', '2026.10-draft.1')
            ->where('rows.1.cells.status', 'Draft'));

    $this->actingAs($admin)
        ->get(route('admin.suspect-assessments.index', ['refer' => '']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->has('rows', 1)
            ->where('rows.0.cells.patient_name', 'Test Patient')
            ->where('rows.0.cells.register_number', 'REG-TEST-1')
            ->where('rows.0.cells.age_band', '15 to 24')
            ->where('rows.0.cells.score', 'In progress'));

    expect(SuspectAssessment::query()->count())->toBe(1);
});

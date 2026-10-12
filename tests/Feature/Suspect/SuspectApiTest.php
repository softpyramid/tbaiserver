<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\SuspectAssessment;
use App\Models\SuspectAssessmentFlag;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    enableFeature('suspect_screening');
    suspectDraftRuleSet();
});

/**
 * @return array{0: User, 1: Facility, 2: string}
 */
function startSuspectScreening(TeamRole $role = TeamRole::Member): array
{
    [$user, $facility] = suspectStaff($role);

    $response = test()->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'age_band' => '25_44',
            'sex' => 'female',
        ])
        ->assertCreated();

    return [$user, $facility, (string) $response->json('assessment_id')];
}

test('the rule set endpoint returns the draft with its labels and never a probability', function () {
    [$user] = suspectStaff();

    $this->actingAs($user)
        ->getJson(route('api.suspect.rule-set'))
        ->assertOk()
        ->assertJsonPath('version', '2026.10-draft.1')
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('engine_version', '1.0.0')
        ->assertJsonPath('is_probability', false)
        ->assertJsonPath('score_label', 'Provisional prioritisation score, not a probability')
        ->assertJsonPath('thresholds', ['high' => 40, 'medium' => 20, 'refer' => 40])
        ->assertJsonPath('questions.3.key', 'cough_duration')
        ->assertJsonPath('questions.3.ask_when', ['eq' => ['cough_current', 'yes']])
        ->assertJsonPath('score_items.1.points', 10)
        ->assertJsonCount(19, 'questions')
        ->assertJsonCount(14, 'score_items');
});

test('start, answer and complete a screening', function () {
    [$user, $facility, $id] = startSuspectScreening();

    $assessment = SuspectAssessment::query()->where('uuid', $id)->firstOrFail();

    expect($assessment->facility_id)->toBe($facility->id)
        ->and($assessment->city_id)->toBe($facility->city_id)
        ->and($assessment->worker_role)->toBe('member')
        ->and($assessment->status)->toBe('in_progress');

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'yes']])
        ->assertOk()
        ->assertJsonPath('next_questions.0.key', 'cough_duration')
        ->assertJsonPath('next_questions.0.why', 'Asked because cough is yes.')
        ->assertJsonPath('scores.symptom', 8)
        ->assertJsonPath('scores.is_probability', false)
        ->assertJsonPath('testing_criterion_met', false)
        ->assertJsonPath('clinical_rule_status.status', 'not_configured')
        ->assertJsonPath('alerts', []);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => [
            'cough_duration' => 'gte_2_weeks',
            'hemoptysis' => 'yes',
            'close_contact' => 'unknown',
        ]])
        ->assertOk()
        ->assertJsonPath('scores.symptom', 18)
        ->assertJsonPath('alerts', ['HEMOPTYSIS_CLINICAL_REVIEW'])
        ->assertJsonPath('unknown_items', ['close_contact']);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.complete', $id))
        ->assertOk()
        ->assertJsonPath('assessment_id', $id)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('engine_version', '1.0.0')
        ->assertJsonPath('rule_set_version', '2026.10-draft.1')
        ->assertJsonPath('rule_set_status', 'draft')
        ->assertJsonPath('scores', ['symptom' => 18, 'exposure' => 0, 'history' => 0, 'raw' => 18, 'score' => 18, 'is_probability' => false])
        ->assertJsonPath('alerts', ['HEMOPTYSIS_CLINICAL_REVIEW'])
        ->assertJsonPath('priority', 'evaluation_required')
        ->assertJsonPath('refer_for_testing', true)
        ->assertJsonPath('recommended_next_action', 'Clinical review per escalation protocol')
        ->assertJsonPath('clinical_rule_status.testing_criterion_met', false)
        ->assertJsonPath('follow_up', null)
        ->assertJsonPath('epidemiological_context', null)
        ->assertJsonPath('responses.0', ['key' => 'cough_current', 'value' => 'yes']);

    $assessment->refresh();

    expect($assessment->status)->toBe('completed')
        ->and($assessment->score)->toBe(18)
        ->and($assessment->unknown_items)->toContain('close_contact')
        ->and($assessment->flags()->where('type', SuspectAssessmentFlag::TYPE_CLINICAL_RULE)->pluck('code')->all())
        ->toBe(['HEMOPTYSIS_CLINICAL_REVIEW']);

    $this->actingAs($user)
        ->getJson(route('api.suspect.assessments.show', $id))
        ->assertOk()
        ->assertJsonPath('scores.score', 18)
        ->assertJsonPath('refer_for_testing', true);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['fever' => 'yes']])
        ->assertStatus(409);
});

test('an answer outside the options is rejected', function () {
    [$user, , $id] = startSuspectScreening();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'maybe']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers.cough_current');

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'not_disclosed']])
        ->assertStatus(422);
});

test('an unknown question key is rejected', function () {
    [$user, , $id] = startSuspectScreening();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['shoe_size' => 'yes']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers.shoe_size');

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['age_band' => '25_44']])
        ->assertStatus(422);
});

test('personal fields are rejected on every endpoint', function (string $field) {
    [$user, $facility, $id] = startSuspectScreening();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'age_band' => '25_44',
            'sex' => 'female',
            $field => 'Someone',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => [$field => 'x', 'cough_current' => 'yes']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers.'.$field);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.complete', $id), [$field => 'x'])
        ->assertStatus(422);

    $this->actingAs($user)
        ->getJson(route('api.suspect.rule-set', [$field => 'x']))
        ->assertStatus(422);

    expect(SuspectAssessment::query()->count())->toBe(1)
        ->and(SuspectAssessment::query()->first()->answers()->count())->toBe(0);
})->with(['name', 'phone', 'national_id', 'nationalId', 'cnic', 'patient_name', 'mr_number']);

test('staff of another facility cannot see or change a screening', function () {
    [, , $id] = startSuspectScreening();
    [$other] = suspectStaff(TeamRole::Owner);

    $this->actingAs($other)->getJson(route('api.suspect.assessments.show', $id))->assertNotFound();
    $this->actingAs($other)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'yes']])
        ->assertNotFound();
    $this->actingAs($other)->postJson(route('api.suspect.assessments.complete', $id))->assertNotFound();
});

test('staff cannot start a screening at a facility they do not belong to', function () {
    [$user] = suspectStaff();
    [, $otherFacility] = suspectStaff();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $otherFacility->team->slug,
            'age_band' => '25_44',
            'sex' => 'male',
        ])
        ->assertForbidden();
});

test('a programme viewer cannot start a screening', function () {
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => Province::factory()->create()->id,
    ]);
    [, $facility] = suspectStaff();

    $this->actingAs($viewer)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'age_band' => '25_44',
            'sex' => 'male',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)->getJson(route('api.suspect.rule-set'))->assertForbidden();

    expect(SuspectAssessment::query()->count())->toBe(0);
});

test('a tb-ai admin can read a screening but not answer it', function () {
    [$user, , $id] = startSuspectScreening();
    $this->actingAs($user)->postJson(route('api.suspect.assessments.complete', $id))->assertOk();
    $admin = suspectAdmin();

    $this->actingAs($admin)
        ->getJson(route('api.suspect.assessments.show', $id))
        ->assertOk()
        ->assertJsonPath('assessment_id', $id)
        ->assertJsonPath('scores.is_probability', false);

    $this->actingAs($admin)->getJson(route('api.suspect.rule-set'))->assertOk();
});

test('the api answers not found while the flag is off', function () {
    config(['tbai.features.suspect_screening' => false]);
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)->getJson(route('api.suspect.rule-set'))->assertNotFound();
    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'age_band' => '25_44',
            'sex' => 'male',
        ])
        ->assertNotFound();
});

test('production with no approved published rule set answers 503', function () {
    [$user, $facility] = suspectStaff();
    // Outside the testing environment the CSRF check runs; this test is about the release gate.
    $this->withoutMiddleware(PreventRequestForgery::class);
    app()->instance('env', 'production');
    $message = 'Suspect screening is not available until a clinically approved rule set is published.';

    $this->actingAs($user)
        ->getJson(route('api.suspect.rule-set'))
        ->assertStatus(503)
        ->assertExactJson(['message' => $message]);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'age_band' => '25_44',
            'sex' => 'male',
        ])
        ->assertStatus(503)
        ->assertExactJson(['message' => $message]);

    expect(SuspectAssessment::query()->count())->toBe(0);
});

test('signed out requests get a json 401', function () {
    $this->getJson(route('api.suspect.rule-set'))->assertUnauthorized();
});

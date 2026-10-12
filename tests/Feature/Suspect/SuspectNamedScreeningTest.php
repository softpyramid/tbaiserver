<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\SuspectAssessment;
use App\Models\User;
use App\Support\Suspect\AssessmentRecorder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('suspect_screening');
    suspectDraftRuleSet();
});

/**
 * @return array<string, string>
 */
function namedStartPayload(string $facility, array $overrides = []): array
{
    return [
        'facility' => $facility,
        'patient_name' => 'Test Patient',
        'register_number' => 'REG-TEST-7',
        'age_band' => '25_44',
        'sex' => 'female',
        ...$overrides,
    ];
}

function provincialViewer(): User
{
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => Province::factory()->create()->id,
    ]);

    return $viewer->fresh() ?? $viewer;
}

test('starting a screening needs a patient name and a register number', function (string $missing) {
    [$user, $facility] = suspectStaff();
    $payload = namedStartPayload($facility->team->slug);
    unset($payload[$missing]);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($missing);

    expect(SuspectAssessment::query()->count())->toBe(0);
})->with(['patient_name', 'register_number']);

test('a blank or too long name or register number is rejected', function (array $overrides, string $field) {
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug, $overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    [['patient_name' => '   '], 'patient_name'],
    [['patient_name' => str_repeat('a', 121)], 'patient_name'],
    [['register_number' => ''], 'register_number'],
    [['register_number' => str_repeat('1', 51)], 'register_number'],
]);

test('the name and register number are stored and returned to the facility staff', function () {
    [$user, $facility] = suspectStaff(TeamRole::Admin);

    $id = $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug, [
            'patient_name' => str_repeat('a', 120),
            'register_number' => str_repeat('1', 50),
        ]))
        ->assertCreated()
        ->assertJsonPath('patient_name', str_repeat('a', 120))
        ->assertJsonPath('register_number', str_repeat('1', 50))
        ->json('assessment_id');

    $assessment = SuspectAssessment::query()->where('uuid', $id)->sole();

    expect($assessment->patient_name)->toBe(str_repeat('a', 120))
        ->and($assessment->register_number)->toBe(str_repeat('1', 50));

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'no']])
        ->assertOk()
        ->assertJsonPath('register_number', str_repeat('1', 50));

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.complete', $id))
        ->assertOk()
        ->assertJsonPath('patient_name', str_repeat('a', 120))
        ->assertJsonPath('register_number', str_repeat('1', 50));

    $this->actingAs($user)
        ->getJson(route('api.suspect.assessments.show', $id))
        ->assertOk()
        ->assertJsonPath('patient_name', str_repeat('a', 120));

    [$colleague] = suspectStaff(TeamRole::Member, $facility);

    $this->actingAs($colleague)
        ->getJson(route('api.suspect.assessments.show', $id))
        ->assertOk()
        ->assertJsonPath('register_number', str_repeat('1', 50));
});

test('a tb-ai admin sees the name and register number in the api, the result page and the admin list and csv', function () {
    [$user, $facility] = suspectStaff();
    $id = $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug))
        ->assertCreated()
        ->json('assessment_id');
    $admin = suspectAdmin();

    $this->actingAs($admin)
        ->getJson(route('api.suspect.assessments.show', $id))
        ->assertOk()
        ->assertJsonPath('patient_name', 'Test Patient')
        ->assertJsonPath('register_number', 'REG-TEST-7');

    $this->actingAs($admin)
        ->get(route('suspect.show', [$facility->team, $id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.patient_name', 'Test Patient')
            ->where('result.register_number', 'REG-TEST-7'));

    $this->actingAs($admin)
        ->get(route('admin.suspect-assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->where('columns.2', ['key' => 'patient_name', 'label' => 'Patient name'])
            ->where('columns.3', ['key' => 'register_number', 'label' => 'Register number'])
            ->where('rows.0.cells.patient_name', 'Test Patient')
            ->where('rows.0.cells.register_number', 'REG-TEST-7'));

    $csv = $this->actingAs($admin)
        ->get(route('admin.suspect-assessments.index', ['export' => 'csv']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('"Patient name","Register number"')
        ->and($csv)->toContain('"Test Patient",REG-TEST-7');
});

test('a name a spreadsheet would read as a formula is exported as text', function () {
    [$user, $facility] = suspectStaff();
    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug, [
            'patient_name' => '=1+1',
            'register_number' => '-7',
        ]))
        ->assertCreated();

    $csv = $this->actingAs(suspectAdmin())
        ->get(route('admin.suspect-assessments.index', ['export' => 'csv']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain("'=1+1,'-7");
});

test('the facility list shows the name and register number to staff', function () {
    [$user, $facility] = suspectStaff();
    app(AssessmentRecorder::class)->start($user, $facility, suspectDraftRuleSet(), 'Test Patient', 'REG-TEST-7', '25_44', 'female');

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.patientName', 'Test Patient')
            ->where('rows.0.registerNumber', 'REG-TEST-7'));
});

test('a programme viewer cannot start, read or list named screenings', function () {
    [$user, $facility] = suspectStaff();
    $assessment = app(AssessmentRecorder::class)->start($user, $facility, suspectDraftRuleSet(), 'Test Patient', 'REG-TEST-7', '25_44', 'female');
    $viewer = provincialViewer();

    $this->actingAs($viewer)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug))
        ->assertForbidden();
    $this->actingAs($viewer)
        ->getJson(route('api.suspect.assessments.show', $assessment->uuid))
        ->assertNotFound()
        ->assertDontSee('Test Patient')
        ->assertDontSee('REG-TEST-7');
    $this->actingAs($viewer)->get(route('suspect.show', [$facility->team, $assessment->uuid]))->assertForbidden();
    $this->actingAs($viewer)->get(route('suspect.index', $facility->team))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.suspect-assessments.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.suspect-assessments.index', ['export' => 'csv']))->assertForbidden();

    expect(SuspectAssessment::query()->count())->toBe(1);
});

test('staff of another facility get not found for a named screening', function () {
    [$user, $facility] = suspectStaff();
    $assessment = app(AssessmentRecorder::class)->start($user, $facility, suspectDraftRuleSet(), 'Test Patient', 'REG-TEST-7', '25_44', 'female');
    [$other, $otherFacility] = suspectStaff(TeamRole::Owner);

    $this->actingAs($other)
        ->getJson(route('api.suspect.assessments.show', $assessment->uuid))
        ->assertNotFound()
        ->assertDontSee('Test Patient');
    $this->actingAs($other)
        ->postJson(route('api.suspect.assessments.complete', $assessment->uuid))
        ->assertNotFound();
    $this->actingAs($other)
        ->get(route('suspect.show', [$otherFacility->team, $assessment->uuid]))
        ->assertNotFound();
    $this->actingAs($other)
        ->get(route('suspect.index', $otherFacility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
});

test('the name and register number are refused inside answers, on other endpoints and in a query string', function (string $field) {
    [$user, $facility] = suspectStaff();
    $id = $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug))
        ->assertCreated()
        ->json('assessment_id');

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => [$field => 'x', 'cough_current' => 'yes']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers.'.$field);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), [$field => 'x', 'answers' => ['cough_current' => 'yes']])
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.complete', $id), [$field => 'x'])
        ->assertStatus(422);

    $this->actingAs($user)
        ->getJson(route('api.suspect.rule-set', [$field => 'x']))
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store', [$field => 'x']), namedStartPayload($facility->team->slug))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(SuspectAssessment::query()->count())->toBe(1)
        ->and(SuspectAssessment::query()->sole()->answers()->count())->toBe(0);
})->with(['patient_name', 'register_number']);

test('the start request accepts a register number but still refuses other identifiers', function (string $field) {
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), namedStartPayload($facility->team->slug, [$field => 'x']))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field)
        ->assertJsonPath('errors.'.$field.'.0', 'Do not record a phone number, CNIC, national ID or MR number here.');

    expect(SuspectAssessment::query()->count())->toBe(0);
})->with(['name', 'phone', 'national_id', 'nationalId', 'cnic', 'mr_number']);

test('screenings recorded before named screenings show without a name', function () {
    [$user, $facility] = suspectStaff();
    $assessment = app(AssessmentRecorder::class)->start($user, $facility, suspectDraftRuleSet(), 'Test Patient', 'REG-TEST-7', '25_44', 'female');
    $assessment->forceFill(['patient_name' => null, 'register_number' => null])->save();

    $this->actingAs($user)
        ->getJson(route('api.suspect.assessments.show', $assessment->uuid))
        ->assertOk()
        ->assertJsonPath('patient_name', null)
        ->assertJsonPath('register_number', null);

    $this->actingAs(suspectAdmin())
        ->get(route('admin.suspect-assessments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.cells.patient_name', ''));
});

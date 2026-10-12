<?php

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\SuspectAssessment;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('patients', 'suspect_screening');
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Karachi'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: User, 1: Facility, 2: Patient}
 */
function patStaffPatient(TeamRole $role = TeamRole::Member): array
{
    [$user, $facility] = suspectStaff($role);
    $patient = Patient::factory()->create(['facility_id' => $facility->id, 'name' => 'Test Patient Follow', 'register_number' => 'FU-1']);

    return [$user, $facility, $patient];
}

/**
 * @return array<string, string>
 */
function patTreatmentPayload(array $overrides = []): array
{
    return [
        'start_date' => '2026-01-31',
        'regimen_category' => 'category_i_new',
        'registration_type' => 'new',
        'site' => 'pulmonary',
        'tb03_number' => 'TB03-9',
        'notes' => '',
        ...$overrides,
    ];
}

function patScreening(Facility $facility, array $overrides = []): SuspectAssessment
{
    $ruleSet = suspectDraftRuleSet();

    return SuspectAssessment::query()->create([
        'facility_id' => $facility->id,
        'patient_name' => 'Test Patient Screened',
        'register_number' => 'SCR-5',
        'age_band' => '25_44',
        'sex' => 'male',
        'rule_set_id' => $ruleSet->id,
        'engine_version' => $ruleSet->engine_version,
        'status' => SuspectAssessment::STATUS_IN_PROGRESS,
        ...$overrides,
    ]);
}

test('starting a treatment schedules the right number of monthly visits', function (string $regimen, int $count) {
    [$user, $facility, $patient] = patStaffPatient();

    $this->actingAs($user)
        ->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['regimen_category' => $regimen]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('patients.show', [$facility->team, $patient]));

    $treatment = Treatment::query()->sole();

    expect($treatment->visits()->count())->toBe($count)
        ->and($treatment->visits->pluck('month_number')->all())->toBe(range(1, $count))
        ->and($treatment->isActive())->toBeTrue();
})->with([
    ['category_i_new', 6],
    ['child', 6],
    ['category_ii_retreatment', 8],
    ['dr_tb', 9],
]);

test('visit dates are the start date plus n months without overflow', function () {
    [$user, $facility, $patient] = patStaffPatient();

    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload());

    $dates = Treatment::query()->sole()->visits->map(fn (TreatmentVisit $visit) => $visit->scheduled_date->toDateString())->all();

    expect($dates)->toBe(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31']);
});

test('a treatment cannot start in the future or with unknown values', function (array $overrides, string $field) {
    [$user, $facility, $patient] = patStaffPatient();

    $this->actingAs($user)
        ->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Treatment::query()->count())->toBe(0);
})->with([
    [['start_date' => '2026-10-13'], 'start_date'],
    [['start_date' => '12/10/2026'], 'start_date'],
    [['regimen_category' => 'category_iii'], 'regimen_category'],
    [['registration_type' => 'returned'], 'registration_type'],
    [['site' => 'lungs'], 'site'],
]);

test('a patient has at most one active treatment', function () {
    [$user, $facility, $patient] = patStaffPatient();

    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload())->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['start_date' => '2026-03-01']))
        ->assertSessionHasErrors(['start_date' => 'This patient already has an active treatment. Record its outcome first.']);

    expect(Treatment::query()->count())->toBe(1)
        ->and(TreatmentVisit::query()->count())->toBe(6);
});

test('an outcome closes the treatment and cancels later unattended visits only', function () {
    [$user, $facility, $patient] = patStaffPatient();
    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['start_date' => '2026-05-10']));
    $treatment = Treatment::query()->sole();
    // Visits: 06-10, 07-10, 08-10, 09-10, 10-10, 11-10. Month 1 attended, month 2 missed.
    $treatment->visits()->where('month_number', 1)->update(['attended_date' => '2026-06-10']);

    $this->actingAs($user)
        ->patch(route('patients.treatments.outcome', [$facility->team, $patient, $treatment]), [
            'outcome' => 'lost_to_follow_up',
            'outcome_date' => '2026-08-01',
        ])
        ->assertSessionHasNoErrors();

    $treatment->refresh();
    $visits = $treatment->visits()->get()->keyBy('month_number');

    expect($treatment->outcome?->value)->toBe('lost_to_follow_up')
        ->and($treatment->outcome_date?->toDateString())->toBe('2026-08-01')
        ->and($treatment->isActive())->toBeFalse()
        ->and($visits[1]->cancelled_at)->toBeNull()
        ->and($visits[2]->cancelled_at)->toBeNull()
        ->and($visits->only([3, 4, 5, 6])->every(fn (TreatmentVisit $visit) => $visit->cancelled_at !== null))->toBeTrue()
        ->and(TreatmentVisit::query()->count())->toBe(6);

    $this->actingAs($user)
        ->patch(route('patients.treatments.outcome', [$facility->team, $patient, $treatment]), ['outcome' => 'cured', 'outcome_date' => '2026-09-01'])
        ->assertSessionHasErrors('outcome');

    // A new treatment may start once the previous one is closed.
    $this->actingAs($user)
        ->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['start_date' => '2026-09-01', 'registration_type' => 'treatment_after_loss_to_follow_up']))
        ->assertSessionHasNoErrors();

    expect(Treatment::query()->count())->toBe(2);

    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTreatment.startDateIso', '2026-09-01')
            ->has('pastTreatments', 1)
            ->where('pastTreatments.0.outcomeLabel', 'Lost to follow-up')
            ->where('pastTreatments.0.visits.0.status', 'attended')
            ->where('pastTreatments.0.visits.1.status', 'missed')
            ->where('pastTreatments.0.visits.2.status', 'cancelled'));
});

test('an outcome date must fall between the start and today', function (string $date) {
    [$user, $facility, $patient] = patStaffPatient();
    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['start_date' => '2026-05-10']));
    $treatment = Treatment::query()->sole();

    $this->actingAs($user)
        ->patch(route('patients.treatments.outcome', [$facility->team, $patient, $treatment]), ['outcome' => 'cured', 'outcome_date' => $date])
        ->assertSessionHasErrors('outcome_date');

    expect($treatment->fresh()?->isActive())->toBeTrue();
})->with(['2026-05-09', '2026-10-13']);

test('staff record a visit and the audit row lists field names only', function () {
    [$user, $facility, $patient] = patStaffPatient(TeamRole::Screener);
    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['start_date' => '2026-05-10']));
    $visit = TreatmentVisit::query()->where('month_number', 2)->sole();

    $this->actingAs($user)
        ->patch(route('patients.visits.update', [$facility->team, $patient, $visit]), [
            'attended_date' => '2026-07-12',
            'sputum_result' => 'negative',
            'notes' => 'Doing well',
        ])
        ->assertSessionHasNoErrors();

    $visit->refresh();
    expect($visit->attended_date?->toDateString())->toBe('2026-07-12')
        ->and($visit->sputum_result?->value)->toBe('negative')
        ->and($visit->notes)->toBe('Doing well')
        ->and($visit->updated_by)->toBe($user->id);

    $audit = AuditLog::query()->where('action', 'treatment_visit.updated')->sole();
    expect($audit->properties['fields'])->toEqualCanonicalizing(['attended_date', 'sputum_result', 'notes'])
        ->and($audit->properties['month'])->toBe(2);

    $this->actingAs($user)
        ->patch(route('patients.visits.update', [$facility->team, $patient, $visit]), ['attended_date' => '2026-10-13'])
        ->assertSessionHasErrors('attended_date');
    $this->actingAs($user)
        ->patch(route('patients.visits.update', [$facility->team, $patient, $visit]), ['sputum_result' => 'maybe'])
        ->assertSessionHasErrors('sputum_result');
});

test('a cancelled visit cannot be edited and another patient visit is 404', function () {
    [$user, $facility, $patient] = patStaffPatient();
    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['start_date' => '2026-05-10']));
    $visit = TreatmentVisit::query()->where('month_number', 6)->sole();
    $visit->forceFill(['cancelled_at' => now()])->save();

    $this->actingAs($user)
        ->patch(route('patients.visits.update', [$facility->team, $patient, $visit]), ['notes' => 'x'])
        ->assertSessionHasErrors('attended_date');

    $otherPatient = Patient::factory()->create(['facility_id' => $facility->id]);
    $this->actingAs($user)
        ->patch(route('patients.visits.update', [$facility->team, $otherPatient, $visit]), ['notes' => 'x'])
        ->assertNotFound();
    $this->actingAs($user)
        ->patch(route('patients.treatments.outcome', [$facility->team, $otherPatient, $visit->treatment]), ['outcome' => 'cured', 'outcome_date' => '2026-09-01'])
        ->assertNotFound();
});

test('staff of another facility and programme viewers cannot touch treatments', function () {
    [$user, $facility, $patient] = patStaffPatient();
    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload());
    $treatment = Treatment::query()->sole();
    $visit = $treatment->visits()->first();
    [$other] = suspectStaff(TeamRole::Owner);

    foreach ([$other, patViewer()] as $outsider) {
        $this->actingAs($outsider)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload())->assertNotFound();
        $this->actingAs($outsider)->patch(route('patients.treatments.outcome', [$facility->team, $patient, $treatment]), ['outcome' => 'cured', 'outcome_date' => '2026-09-01'])->assertNotFound();
        $this->actingAs($outsider)->patch(route('patients.visits.update', [$facility->team, $patient, $visit]), ['notes' => 'x'])->assertNotFound();
    }

    expect($treatment->fresh()?->isActive())->toBeTrue();
});

test('treatment start and outcome are audited without patient identifiers', function () {
    [$user, $facility, $patient] = patStaffPatient();
    $this->actingAs($user)->post(route('patients.treatments.store', [$facility->team, $patient]), patTreatmentPayload(['regimen_category' => 'dr_tb']));
    $treatment = Treatment::query()->sole();
    $this->actingAs($user)->patch(route('patients.treatments.outcome', [$facility->team, $patient, $treatment]), ['outcome' => 'cured', 'outcome_date' => '2026-10-01']);

    $started = AuditLog::query()->where('action', 'treatment.started')->sole();
    $closed = AuditLog::query()->where('action', 'treatment.outcome_recorded')->sole();

    expect($started->properties)->toMatchArray(['treatment_id' => $treatment->id, 'regimen_category' => 'dr_tb', 'visits' => 9])
        ->and($closed->properties)->toMatchArray(['outcome' => 'cured', 'cancelled_visits' => 1])
        ->and(AuditLog::query()->get()->toJson())->not->toContain('Test Patient Follow');
});

test('create from screening prefills the form and links the screening', function () {
    [$user, $facility] = suspectStaff();
    $screening = patScreening($facility);

    $this->actingAs($user)->get(route('patients.create', [$facility->team, 'screening' => $screening->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('screening', $screening->uuid)
            ->where('patient.name', 'Test Patient Screened')
            ->where('patient.registerNumber', 'SCR-5')
            ->where('patient.gender', 'male'));

    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload([
        'name' => 'Test Patient Screened',
        'register_number' => 'SCR-5',
        'gender' => 'male',
        'screening' => $screening->uuid,
    ]))->assertSessionHasNoErrors();

    $patient = Patient::query()->sole();
    expect($screening->fresh()?->patient_id)->toBe($patient->id);

    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))
        ->assertInertia(fn (Assert $page) => $page->has('screenings', 1)->where('screenings.0.id', $screening->uuid));

    $this->actingAs($user)->get(route('suspect.show', [$facility->team, $screening]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('patientRecord.linked.registerNumber', 'SCR-5')
            ->where('patientRecord.linked.href', route('patients.show', [$facility->team, $patient])));
});

test('a screening from another facility is never prefilled or linked on create', function () {
    [$user, $facility] = suspectStaff();
    $foreign = patScreening(Facility::factory()->create(), ['patient_name' => 'Test Patient Foreign']);

    $this->actingAs($user)->get(route('patients.create', [$facility->team, 'screening' => $foreign->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('screening', null)->where('patient.name', ''));

    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload(['screening' => $foreign->uuid]))->assertSessionHasNoErrors();

    expect($foreign->fresh()?->patient_id)->toBeNull();
});

test('link existing patient by register number within the facility only', function () {
    [$user, $facility] = suspectStaff(TeamRole::Screener);
    $screening = patScreening($facility);
    $patient = Patient::factory()->create(['facility_id' => $facility->id, 'register_number' => 'SCR-5']);
    Patient::factory()->create(['register_number' => 'ELSEWHERE-1']);

    $this->actingAs($user)->get(route('suspect.show', [$facility->team, $screening]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('patientRecord.linked', null)
            ->where('patientRecord.registerNumber', 'SCR-5'));

    $this->actingAs($user)
        ->post(route('suspect.patient.link', [$facility->team, $screening]), ['register_number' => 'ELSEWHERE-1'])
        ->assertSessionHasErrors(['register_number' => 'No patient at this facility has that register number.']);
    expect($screening->fresh()?->patient_id)->toBeNull();

    $this->actingAs($user)
        ->post(route('suspect.patient.link', [$facility->team, $screening]), ['register_number' => 'SCR-5'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('suspect.show', [$facility->team, $screening]));

    expect($screening->fresh()?->patient_id)->toBe($patient->id)
        ->and(AuditLog::query()->where('action', 'patient.screening_linked')->sole()->properties)->toBe(['screening' => $screening->uuid]);
});

test('linking another facility screening or as an outsider is 404', function () {
    [$user, $facility] = suspectStaff();
    Patient::factory()->create(['facility_id' => $facility->id, 'register_number' => 'SCR-5']);
    $foreign = patScreening(Facility::factory()->create());
    $own = patScreening($facility);

    $this->actingAs($user)->post(route('suspect.patient.link', [$facility->team, $foreign]), ['register_number' => 'SCR-5'])->assertNotFound();
    $this->actingAs(patViewer())->post(route('suspect.patient.link', [$facility->team, $own]), ['register_number' => 'SCR-5'])->assertNotFound();

    expect($foreign->fresh()?->patient_id)->toBeNull()
        ->and($own->fresh()?->patient_id)->toBeNull();
});

test('the screening page offers no patient actions while patients is off', function () {
    config(['tbai.features.patients' => false]);
    [$user, $facility] = suspectStaff();
    $screening = patScreening($facility);

    $this->actingAs($user)->get(route('suspect.show', [$facility->team, $screening]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('patientRecord', null));
});

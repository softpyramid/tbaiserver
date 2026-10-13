<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\Patients\PatientIdentifiers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('patients');
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Karachi'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array<string, mixed>
 */
function patPayload(array $overrides = []): array
{
    return [
        'name' => 'Test Patient Alpha',
        'cnic' => '00000-1234567-1',
        'phone' => '0300 0000001',
        'gender' => 'female',
        'age' => 30,
        'register_number' => 'REG-1',
        'informed_of_data_use' => true,
        ...$overrides,
    ];
}

function patViewer(ProgrammeRole $role = ProgrammeRole::ProvincialViewer): User
{
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => $role,
        'scope_type' => ScopeType::Province,
        'scope_id' => Province::factory()->create()->id,
    ]);

    return $viewer->fresh() ?? $viewer;
}

test('staff create a patient with the CNIC and phone normalized', function () {
    [$user, $facility] = suspectStaff(TeamRole::Member);

    $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload())
        ->assertRedirect();

    $patient = Patient::query()->sole();

    expect($patient->name)->toBe('Test Patient Alpha')
        ->and($patient->cnic)->toBe('0000012345671')
        ->and($patient->phone)->toBe('+923000000001')
        ->and($patient->gender->value)->toBe('female')
        ->and($patient->age)->toBe(30)
        ->and($patient->register_number)->toBe('REG-1')
        ->and($patient->facility_id)->toBe($facility->id)
        ->and($patient->informed_of_data_use)->toBeTrue()
        ->and($patient->informed_at)->not->toBeNull()
        ->and($patient->created_by)->toBe($user->id);
});

test('a CNIC must be 13 digits, with or without dashes', function (string $cnic, bool $valid) {
    [$user, $facility] = suspectStaff();

    $response = $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload(['cnic' => $cnic]));

    if ($valid) {
        $response->assertSessionHasNoErrors();
        expect(Patient::query()->sole()->cnic)->toBe(str_replace('-', '', $cnic));
    } else {
        $response->assertSessionHasErrors('cnic');
        expect(Patient::query()->count())->toBe(0);
    }
})->with([
    ['12345-1234567-1', true],
    ['1234512345671', true],
    ['123451234567', false],
    ['12345123456712', false],
    ['1234-51234567-1', false],
    ['12345-1234567-12', false],
    ['abcde-fghijkl-m', false],
]);

test('a CNIC is required from age 18 and optional for a child', function () {
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload(['cnic' => '', 'age' => 18]))
        ->assertSessionHasErrors('cnic');

    $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload(['cnic' => '', 'age' => 17, 'phone' => '']))
        ->assertSessionHasNoErrors();

    $patient = Patient::query()->sole();
    expect($patient->cnic)->toBeNull()
        ->and($patient->cnic_hash)->toBeNull()
        ->and($patient->phone)->toBeNull();
});

test('a phone must be a Pakistani mobile number', function (string $phone, ?string $stored) {
    [$user, $facility] = suspectStaff();

    $response = $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload(['phone' => $phone]));

    if ($stored === null) {
        $response->assertSessionHasErrors('phone');
    } else {
        $response->assertSessionHasNoErrors();
        expect(Patient::query()->sole()->phone)->toBe($stored);
    }
})->with([
    ['03001234567', '+923001234567'],
    ['+923001234567', '+923001234567'],
    ['0300-1234567', '+923001234567'],
    ['0300123456', null],
    ['04001234567', null],
    ['+92300123456', null],
    ['923001234567', null],
    ['+44 7700 900123', null],
]);

test('the patient must be informed of how their data is used', function (mixed $informed) {
    [$user, $facility] = suspectStaff();
    $payload = patPayload();

    if ($informed === null) {
        unset($payload['informed_of_data_use']);
    } else {
        $payload['informed_of_data_use'] = $informed;
    }

    $this->actingAs($user)
        ->post(route('patients.store', $facility->team), $payload)
        ->assertSessionHasErrors(['informed_of_data_use' => 'Confirm that the patient was told how their data is used.']);

    expect(Patient::query()->count())->toBe(0);
})->with([null, false, '0']);

test('age must be from 0 to 120', function (mixed $age, bool $valid) {
    [$user, $facility] = suspectStaff();

    $response = $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload(['age' => $age, 'register_number' => 'R-'.$age]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('age');
})->with([
    [0, true],
    [120, true],
    [-1, false],
    [121, false],
    ['abc', false],
]);

test('name, gender and register number are required', function (string $field) {
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload([$field => '']))
        ->assertSessionHasErrors($field);
})->with(['name', 'gender', 'register_number']);

test('a register number is unique per facility only', function () {
    [$user, $facility] = suspectStaff();
    [$other, $otherFacility] = suspectStaff();

    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload())->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('patients.store', $facility->team), patPayload(['register_number' => ' REG-1 ']))
        ->assertSessionHasErrors(['register_number' => 'Another patient at this facility already has this register number.']);

    $this->actingAs($other)
        ->post(route('patients.store', $otherFacility->team), patPayload())
        ->assertSessionHasNoErrors();

    expect(Patient::query()->count())->toBe(2);
});

test('name, CNIC and phone are encrypted at rest', function () {
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload());

    $raw = (array) DB::table('patients')->first();

    foreach (['name' => 'Test Patient Alpha', 'cnic' => '0000012345671', 'phone' => '+923000000001'] as $column => $plain) {
        expect($raw[$column])->not->toBe($plain)
            ->and($raw[$column])->not->toContain($plain)
            ->and(decrypt($raw[$column], false))->toBe($plain);
    }

    expect($raw['cnic_hash'])->toBe(hash_hmac('sha256', '0000012345671', (string) config('app.key')))
        ->and($raw['register_number'])->toBe('REG-1');
});

test('search finds a patient by exact register number or CNIC hash', function () {
    [$user, $facility] = suspectStaff();
    Patient::factory()->create(['facility_id' => $facility->id, 'name' => 'Test Patient Alpha', 'cnic' => '0000012345671', 'register_number' => 'REG-1']);
    Patient::factory()->create(['facility_id' => $facility->id, 'name' => 'Test Patient Beta', 'cnic' => '0000099999991', 'register_number' => 'REG-2']);
    Patient::factory()->create(['cnic' => '0000012345671', 'register_number' => 'REG-1', 'name' => 'Test Patient Elsewhere']);

    $names = fn (string $q): array => collect(
        $this->actingAs($user)->get(route('patients.index', [$facility->team, 'q' => $q]))
            ->assertOk()
            ->viewData('page')['props']['rows']
    )->pluck('name')->all();

    expect($names('REG-2'))->toBe(['Test Patient Beta'])
        ->and($names('00000-1234567-1'))->toBe(['Test Patient Alpha'])
        ->and($names('0000012345671'))->toBe(['Test Patient Alpha'])
        ->and($names('Test Patient'))->toBe([])
        ->and($names(''))->toEqualCanonicalizing(['Test Patient Alpha', 'Test Patient Beta']);

    expect(PatientIdentifiers::cnicHash('0000012345671'))
        ->toBe(Patient::query()->where('register_number', 'REG-1')->where('facility_id', $facility->id)->value('cnic_hash'));
});

test('every facility role and a TB-AI admin can list, create, view and edit', function (string $who) {
    $facility = Facility::factory()->create();
    $patient = Patient::factory()->create(['facility_id' => $facility->id, 'name' => 'Test Patient Visible']);

    $user = $who === 'admin' ? suspectAdmin() : suspectStaff(TeamRole::from($who), $facility)[0];

    $this->actingAs($user)->get(route('patients.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/index')->where('rows.0.name', 'Test Patient Visible'));
    $this->actingAs($user)->get(route('patients.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/form')->where('mode', 'create'));
    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/show')->where('patient.name', 'Test Patient Visible'));
    $this->actingAs($user)->get(route('patients.edit', [$facility->team, $patient]))->assertOk();
    $this->actingAs($user)
        ->put(route('patients.update', [$facility->team, $patient]), patPayload(['register_number' => $patient->register_number, 'age' => 31]))
        ->assertSessionHasNoErrors();

    expect($patient->fresh()?->age)->toBe(31);
})->with(['owner', 'admin', 'member', 'screener', 'admin_user' => 'admin']);

test('a TB-AI admin can open any facility', function () {
    $facility = Facility::factory()->create();
    Patient::factory()->create(['facility_id' => $facility->id]);

    $this->actingAs(suspectAdmin())
        ->post(route('patients.store', $facility->team), patPayload())
        ->assertSessionHasNoErrors();

    expect(Patient::query()->where('facility_id', $facility->id)->count())->toBe(2);
});

test('staff of another facility get 404 everywhere', function () {
    $facility = Facility::factory()->create();
    $patient = Patient::factory()->create(['facility_id' => $facility->id]);
    [$other, $otherFacility] = suspectStaff(TeamRole::Owner);

    $this->actingAs($other)->get(route('patients.index', $facility->team))->assertNotFound();
    $this->actingAs($other)->get(route('patients.create', $facility->team))->assertNotFound();
    $this->actingAs($other)->post(route('patients.store', $facility->team), patPayload())->assertNotFound();
    $this->actingAs($other)->get(route('patients.show', [$facility->team, $patient]))->assertNotFound();
    $this->actingAs($other)->get(route('patients.edit', [$facility->team, $patient]))->assertNotFound();
    $this->actingAs($other)->put(route('patients.update', [$facility->team, $patient]), patPayload())->assertNotFound();

    // Their own facility URL with someone else's patient.
    $this->actingAs($other)->get(route('patients.show', [$otherFacility->team, $patient]))->assertNotFound();
    $this->actingAs($other)->get(route('patients.edit', [$otherFacility->team, $patient]))->assertNotFound();
    $this->actingAs($other)->put(route('patients.update', [$otherFacility->team, $patient]), patPayload())->assertNotFound();

    expect(Patient::query()->count())->toBe(1);
});

test('programme viewers get 404 on every patient route and no patient data anywhere', function (ProgrammeRole $role) {
    $facility = Facility::factory()->create();
    $patient = Patient::factory()->create([
        'facility_id' => $facility->id,
        'name' => 'Test Patient Hidden',
        'cnic' => '0000077777771',
        'register_number' => 'HIDDEN-77',
    ]);
    $viewer = patViewer($role);

    $this->actingAs($viewer)->get(route('patients.index', $facility->team))->assertNotFound();
    $this->actingAs($viewer)->get(route('patients.show', [$facility->team, $patient]))->assertNotFound();
    $this->actingAs($viewer)->get(route('patients.edit', [$facility->team, $patient]))->assertNotFound();
    $this->actingAs($viewer)->post(route('patients.store', $facility->team), patPayload())->assertNotFound();
    $this->actingAs($viewer)->get(route('admin.patients.index'))->assertForbidden();

    $this->actingAs($viewer)->get(route('notifications.index'))->assertForbidden();

    foreach ([route('command.index'), route('command.map'), route('command.alerts'), route('command.returns')] as $url) {
        $content = $this->actingAs($viewer)->get($url)->assertOk()->getContent();

        expect($content)->not->toContain('Test Patient Hidden')
            ->and($content)->not->toContain('0000077777771')
            ->and($content)->not->toContain('HIDDEN-77');
    }
})->with([ProgrammeRole::ProvincialViewer, ProgrammeRole::CountryViewer]);

test('the admin patients list shows patients across facilities to admins only', function () {
    Patient::factory()->create(['name' => 'Test Patient North', 'register_number' => 'N-1']);
    Patient::factory()->create(['name' => 'Test Patient South', 'register_number' => 'S-1']);

    $this->actingAs(suspectAdmin())
        ->get(route('admin.patients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->has('rows', 2)
            ->where('exportUrl', null));

    [$staff] = suspectStaff(TeamRole::Owner);
    $this->actingAs($staff)->get(route('admin.patients.index'))->assertForbidden();
});

test('audit rows record create, view and update without decrypted values', function () {
    [$user, $facility] = suspectStaff();

    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload());
    $patient = Patient::query()->sole();

    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))->assertOk();
    $this->actingAs($user)->put(route('patients.update', [$facility->team, $patient]), patPayload([
        'name' => 'Test Patient Renamed',
        'phone' => '03009999999',
    ]))->assertSessionHasNoErrors();

    $rows = AuditLog::query()->where('subject_type', Patient::class)->where('subject_id', $patient->id)->orderBy('id')->get();

    expect($rows->pluck('action')->all())->toBe(['patient.created', 'patient.viewed', 'patient.updated'])
        ->and($rows[2]->properties['fields'])->toEqualCanonicalizing(['name', 'phone']);

    $json = AuditLog::query()->get()->toJson();

    foreach (['Test Patient Alpha', 'Test Patient Renamed', '0000012345671', '00000-1234567-1', '+923000000001', '+923009999999', '03009999999'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

test('an unchanged save writes no update audit row', function () {
    [$user, $facility] = suspectStaff();
    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload());
    $patient = Patient::query()->sole();

    $this->actingAs($user)->put(route('patients.update', [$facility->team, $patient]), patPayload())->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('action', 'patient.updated')->count())->toBe(0);
});

test('the create form shows the privacy notice and the required checkbox label', function () {
    [$user, $facility] = suspectStaff(TeamRole::Screener);

    $this->actingAs($user)->get(route('patients.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/form')
            ->where('i18n.entry.patients_privacy_notice', 'Tell the patient how their details will be used before saving.')
            ->where('i18n.entry.patients_informed', 'Patient informed of how their data is used'));
});

test('patient routes answer 404 and the nav hides Patients when the flag is off', function () {
    config(['tbai.features.patients' => false]);
    $facility = Facility::factory()->create();
    $patient = Patient::factory()->create(['facility_id' => $facility->id]);
    [$user] = suspectStaff(TeamRole::Owner, $facility);

    $this->actingAs($user)->get(route('patients.index', $facility->team))->assertNotFound();
    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))->assertNotFound();
    $this->actingAs($user)->post(route('patients.store', $facility->team), patPayload())->assertNotFound();
    $this->actingAs($user)->get(route('notifications.index'))->assertNotFound();
    $this->actingAs(suspectAdmin())->get(route('admin.patients.index'))->assertNotFound();

    $this->actingAs($user)->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null)
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('id')->doesntContain('patients'))
            ->where('nav.groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])->pluck('key')->doesntContain('patients')));
});

test('the nav shows Patients and Notifications to staff and frontline workers when on', function (TeamRole $role, string $url) {
    $facility = Facility::factory()->create();
    [$user] = suspectStaff($role, $facility);

    $this->actingAs($user)->get(str_replace('{team}', $facility->team->slug, $url))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread', 0)
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('id')->contains('patients'))
            ->where('nav.groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])->pluck('key')->intersect(['patients', 'notifications'])->count() === 2));
})->with([
    [TeamRole::Member, '/{team}/returns/today'],
    [TeamRole::Screener, '/{team}/patients'],
]);

test('programme viewers get no bell and no patients nav', function () {
    $viewer = patViewer();

    $this->actingAs($viewer)->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null)
            ->where('nav.groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])->pluck('key')->doesntContain('patients')));
});

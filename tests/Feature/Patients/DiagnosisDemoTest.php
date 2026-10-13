<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Diagnosis;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\Diagnosis\DiagnosisEngine;
use App\Support\Diagnosis\DiagnosisFinding;
use App\Support\Diagnosis\DiagnosisRequest;
use App\Support\Diagnosis\DiagnosisResult;
use App\Support\Diagnosis\SimulatedDiagnosisEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('patients', 'ai_demo');
    Storage::fake('slides');
});

/**
 * @return array{0: User, 1: Facility, 2: Patient}
 */
function dxSetup(TeamRole $role = TeamRole::Member): array
{
    [$user, $facility] = suspectStaff($role);
    $patient = Patient::factory()->create([
        'facility_id' => $facility->id,
        'name' => 'Test Patient Demo',
        'register_number' => 'DX-1',
        'age' => 41,
    ]);

    return [$user, $facility, $patient];
}

/**
 * @param  array<string, mixed>  $payload
 */
function dxPost(User $user, Patient $patient, array $payload, bool $json = false): TestResponse
{
    $url = route('patients.diagnoses.store', [$patient->facility->team, $patient]);
    $payload = ['test_type' => 'tb_zn_smear', ...$payload];

    return $json
        ? test()->actingAs($user)->postJson($url, $payload)
        : test()->actingAs($user)->post($url, $payload);
}

function dxViewer(ProgrammeRole $role = ProgrammeRole::ProvincialViewer): User
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

function dxShowUrl(Diagnosis $diagnosis, string $name = 'patients.diagnoses.show'): string
{
    return route($name, [$diagnosis->patient->facility->team, $diagnosis->patient, $diagnosis]);
}

test('every diagnosis route answers 404 when the flag is off', function () {
    [$user, $facility, $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1']);
    $diagnosis = Diagnosis::query()->sole();

    config(['tbai.features.ai_demo' => false]);

    $this->actingAs($user)->get(route('patients.diagnoses.create', [$facility->team, $patient]))->assertNotFound();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1'])->assertNotFound();
    $this->actingAs($user)->get(dxShowUrl($diagnosis))->assertNotFound();
    $this->actingAs($user)->get(dxShowUrl($diagnosis, 'patients.diagnoses.image'))->assertNotFound();

    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('newDiagnosisUrl', null)->where('diagnoses', []));

    expect(Diagnosis::query()->count())->toBe(1);
});

test('the flag defaults off in production, on in local, staging and testing, and an explicit value wins', function () {
    $config = fn (string $env, array $vars = []): bool => (bool) json_decode((string) shell_exec(
        'cd '.escapeshellarg(base_path()).' && '
        .collect(['TBAI_AI_DEMO' => '', ...$vars])->map(fn ($v, $k) => $k.'='.escapeshellarg($v))->implode(' ')
        .' APP_ENV='.escapeshellarg($env)
        .' php -r \'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(config("tbai.features.ai_demo"));\''
    ), true);

    expect($config('production'))->toBeFalse()
        ->and($config('qa'))->toBeFalse()
        ->and($config('local'))->toBeTrue()
        ->and($config('staging'))->toBeTrue()
        ->and($config('testing'))->toBeTrue()
        ->and($config('production', ['TBAI_AI_DEMO' => 'true']))->toBeTrue()
        ->and($config('staging', ['TBAI_AI_DEMO' => 'false']))->toBeFalse();
});

test('a staff member creates a diagnosis from an upload, stored privately with a thumbnail', function () {
    [$user, $facility, $patient] = dxSetup();

    dxPost($user, $patient, [
        'image_source' => 'upload',
        'image' => UploadedFile::fake()->image('patient-name-slide.jpg', 640, 480),
        'symptoms' => 'Cough for 3 weeks',
        'notes' => 'Second sample',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('patients.diagnoses.create', [$facility->team, $patient]))
        ->assertInertiaFlash('diagnosisUrl', dxShowUrl(Diagnosis::query()->sole()));

    $diagnosis = Diagnosis::query()->sole();

    expect($diagnosis->patient_id)->toBe($patient->id)
        ->and($diagnosis->facility_id)->toBe($facility->id)
        ->and($diagnosis->test_type)->toBe('tb_zn_smear')
        ->and($diagnosis->image_source)->toBe('upload')
        ->and($diagnosis->image_path)->toBe("diagnoses/{$facility->id}/{$diagnosis->uuid}.jpg")
        ->and($diagnosis->thumbnail_path)->toBe("diagnoses/{$facility->id}/{$diagnosis->uuid}-thumb.jpg")
        ->and($diagnosis->image_path)->not->toContain('patient-name')
        ->and($diagnosis->symptoms)->toBe('Cough for 3 weeks')
        ->and($diagnosis->notes)->toBe('Second sample')
        ->and($diagnosis->created_by)->toBe($user->id)
        ->and($diagnosis->analysed_at)->not->toBeNull();

    Storage::disk('slides')->assertExists([$diagnosis->image_path, (string) $diagnosis->thumbnail_path]);

    $raw = (array) DB::table('diagnoses')->first();
    expect($raw['symptoms'])->not->toContain('Cough')
        ->and($raw['notes'])->not->toContain('Second');
});

test('a sample key copies the bundled sample onto the private disk', function () {
    [$user, $facility, $patient] = dxSetup();

    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'slide-2'])->assertSessionHasNoErrors();

    $diagnosis = Diagnosis::query()->sole();

    expect($diagnosis->image_source)->toBe('sample')
        ->and($diagnosis->symptoms)->toBeNull()
        ->and(Storage::disk('slides')->get($diagnosis->image_path))
        ->toBe(file_get_contents(public_path('demo/slides/slide-2.jpg')));
});

test('an unknown sample key or a path is rejected with 422', function (string $sample) {
    [$user, , $patient] = dxSetup();

    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => $sample], json: true)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sample' => 'Pick one of the sample slides.']);

    expect(Diagnosis::query()->count())->toBe(0);
})->with(['zn-9', '../../.env', 'demo/slides/slide-2.jpg', '']);

test('a file that is not a real image is rejected', function () {
    [$user, , $patient] = dxSetup();

    $fake = UploadedFile::fake()->createWithContent('slide.jpg', 'not an image at all');

    dxPost($user, $patient, ['image_source' => 'upload', 'image' => $fake], json: true)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    dxPost($user, $patient, ['image_source' => 'camera'], json: true)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    expect(Diagnosis::query()->count())->toBe(0);
});

test('an image over 10 MB is rejected', function () {
    [$user, , $patient] = dxSetup();

    dxPost($user, $patient, [
        'image_source' => 'camera',
        'image' => UploadedFile::fake()->image('slide.jpg')->size(10241),
    ], json: true)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image' => 'The image must be 10 MB or smaller.']);

    expect(Diagnosis::query()->count())->toBe(0);
});

test('the simulated result is always positive with 3 to 5 boxes inside the image, and deterministic', function () {
    $engine = new SimulatedDiagnosisEngine;
    $counts = [];

    foreach (range(1, 40) as $i) {
        $request = new DiagnosisRequest(sprintf('00000000-0000-4000-8000-%012d', $i), 'tb_zn_smear', 'slides', 'x.jpg');
        $result = $engine->analyse($request);

        expect($result->result)->toBe('positive')
            ->and($result->simulated)->toBeTrue()
            ->and($result->engine)->toBe('simulated')
            ->and($result->grade)->toBeIn(SimulatedDiagnosisEngine::GRADES)
            ->and(count($result->findings))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5)
            ->and($engine->analyse($request))->toEqual($result);

        foreach ($result->findings as $finding) {
            expect($finding->x)->toBeGreaterThanOrEqual(0.0)
                ->and($finding->y)->toBeGreaterThanOrEqual(0.0)
                ->and($finding->w)->toBeGreaterThan(0.0)
                ->and($finding->h)->toBeGreaterThan(0.0)
                ->and($finding->x + $finding->w)->toBeLessThanOrEqual(1.0)
                ->and($finding->y + $finding->h)->toBeLessThanOrEqual(1.0)
                ->and($finding->confidence)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }

        $counts[count($result->findings)] = true;
    }

    expect(array_keys($counts))->toEqualCanonicalizing([3, 4, 5]);
});

test('the stored record matches the engine output for its uuid', function () {
    [$user, , $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1']);

    $diagnosis = Diagnosis::query()->sole();
    $again = (new SimulatedDiagnosisEngine)->analyse(new DiagnosisRequest($diagnosis->uuid, 'tb_zn_smear', 'slides', $diagnosis->image_path));

    expect($diagnosis->result)->toBe('positive')
        ->and($diagnosis->simulated)->toBeTrue()
        ->and($diagnosis->engine)->toBe('simulated')
        ->and($diagnosis->engine_version)->toBe(SimulatedDiagnosisEngine::VERSION)
        ->and($diagnosis->grade)->toBe($again->grade)
        ->and($diagnosis->findings)->toBe($again->findingsArray());
});

test('the uploader is the signed-in user, whatever the request says', function () {
    [$user, $facility, $patient] = dxSetup(TeamRole::Screener);
    $someoneElse = User::factory()->create();

    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1', 'created_by' => $someoneElse->id]);

    $diagnosis = Diagnosis::query()->sole();
    expect($diagnosis->created_by)->toBe($user->id);

    $this->actingAs($user)->get(dxShowUrl($diagnosis))
        ->assertInertia(fn (Assert $page) => $page->where('diagnosis.uploadedBy', $user->name));
});

test('the image is served only to people who may see the patient', function () {
    [$user, $facility, $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1']);
    $diagnosis = Diagnosis::query()->sole();
    $image = dxShowUrl($diagnosis, 'patients.diagnoses.image');
    $thumbnail = dxShowUrl($diagnosis, 'patients.diagnoses.thumbnail');

    [$screener] = suspectStaff(TeamRole::Screener, $facility);
    [$other, $otherFacility] = suspectStaff(TeamRole::Owner);

    foreach ([$user, $screener, suspectAdmin()] as $allowed) {
        $response = $this->actingAs($allowed)->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
        $this->actingAs($allowed)->get($thumbnail)->assertOk();
    }

    $this->actingAs($other)->get($image)->assertNotFound();
    $this->actingAs($other)->get(route('patients.diagnoses.image', [$otherFacility->team, $patient, $diagnosis]))->assertNotFound();

    foreach ([ProgrammeRole::ProvincialViewer, ProgrammeRole::CountryViewer] as $role) {
        $viewer = dxViewer($role);
        $this->actingAs($viewer)->get($image)->assertNotFound();
        $this->actingAs($viewer)->get(dxShowUrl($diagnosis))->assertNotFound();
        $this->actingAs($viewer)->get(route('patients.diagnoses.create', [$facility->team, $patient]))->assertNotFound();
        dxPost($viewer, $patient, ['image_source' => 'sample', 'sample' => 'zn-1'])->assertNotFound();
    }

    auth()->logout();
    $this->get($image)->assertRedirect(route('login'));
    $this->get(dxShowUrl($diagnosis))->assertRedirect(route('login'));

    // Another patient's URL with this diagnosis does not open it.
    $otherPatient = Patient::factory()->create(['facility_id' => $facility->id]);
    $this->actingAs($user)->get(route('patients.diagnoses.image', [$facility->team, $otherPatient, $diagnosis]))->assertNotFound();
});

test('audit rows record create and view without patient details or free text', function () {
    [$user, , $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1', 'symptoms' => 'Night sweats']);
    $diagnosis = Diagnosis::query()->sole();

    $this->actingAs($user)->get(dxShowUrl($diagnosis))->assertOk();

    $created = AuditLog::query()->where('action', 'diagnosis.created')->sole();
    $viewed = AuditLog::query()->where('action', 'diagnosis.viewed')->sole();

    expect($created->user_id)->toBe($user->id)
        ->and($created->subject_type)->toBe(Diagnosis::class)
        ->and($created->subject_id)->toBe($diagnosis->id)
        ->and($created->properties)->toMatchArray(['image_source' => 'sample', 'engine' => 'simulated', 'simulated' => true])
        ->and($viewed->subject_id)->toBe($diagnosis->id);

    $json = (string) json_encode([$created->properties, $viewed->properties]);
    expect($json)->not->toContain('Test Patient Demo')
        ->and($json)->not->toContain('DX-1')
        ->and($json)->not->toContain('Night sweats');
});

test('the engine resolves to the simulated one and can be swapped in the container', function () {
    expect(app(DiagnosisEngine::class))->toBeInstanceOf(SimulatedDiagnosisEngine::class);

    app()->instance(DiagnosisEngine::class, new class implements DiagnosisEngine
    {
        public function analyse(DiagnosisRequest $request): DiagnosisResult
        {
            return new DiagnosisResult('negative', null, [new DiagnosisFinding(0.1, 0.1, 0.2, 0.2, 'Test', 0.5)], 'fake', '9', false);
        }
    });

    [$user, , $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1']);

    $diagnosis = Diagnosis::query()->sole();
    expect($diagnosis->result)->toBe('negative')
        ->and($diagnosis->engine)->toBe('fake')
        ->and($diagnosis->simulated)->toBeFalse();

    config(['tbai.diagnosis.engine' => 'nope']);
    app()->forgetInstance(DiagnosisEngine::class);
    expect(fn () => app(DiagnosisEngine::class))->toThrow(InvalidArgumentException::class);
});

test('the patient record lists past diagnoses with a new diagnosis link', function () {
    [$user, $facility, $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1']);
    $diagnosis = Diagnosis::query()->sole();

    $this->actingAs($user)->get(route('patients.show', [$facility->team, $patient]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/show')
            ->where('newDiagnosisUrl', route('patients.diagnoses.create', [$facility->team, $patient]))
            ->has('diagnoses', 1)
            ->where('diagnoses.0.id', $diagnosis->uuid)
            ->where('diagnoses.0.resultLabel', 'POSITIVE')
            ->where('diagnoses.0.testLabel', 'TB (ZN smear microscopy)')
            ->where('diagnoses.0.href', dxShowUrl($diagnosis)));
});

test('the create page offers the TB test and the two samples', function () {
    [$user, $facility, $patient] = dxSetup(TeamRole::Screener);

    $this->actingAs($user)->get(route('patients.diagnoses.create', [$facility->team, $patient]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/diagnoses/create')
            ->where('testType', 'tb_zn_smear')
            ->where('maxMegabytes', 10)
            ->has('samples', 2)
            ->where('samples.0.key', 'zn-1')
            ->where('samples.1.key', 'slide-2'));
});

test('the report page carries the title, the demo banner and the simulated details', function () {
    [$user, , $patient] = dxSetup();
    dxPost($user, $patient, ['image_source' => 'sample', 'sample' => 'zn-1']);
    $diagnosis = Diagnosis::query()->sole();

    $this->actingAs($user)->get(dxShowUrl($diagnosis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/diagnoses/show')
            ->where('title', 'Tuberculosis test report')
            ->where('banner', 'Demonstration only. Result is simulated, not an AI or clinical diagnosis.')
            ->where('gradeNote', 'Illustrative grade')
            ->where('patient.name', 'Test Patient Demo')
            ->where('patient.registerNumber', 'DX-1')
            ->where('patient.age', 41)
            ->where('diagnosis.testLabel', 'TB (ZN smear microscopy)')
            ->where('diagnosis.result', 'positive')
            ->where('diagnosis.resultLabel', 'POSITIVE')
            ->where('diagnosis.engineLabel', 'Simulated engine (demo)')
            ->where('diagnosis.imageUrl', dxShowUrl($diagnosis, 'patients.diagnoses.image'))
            ->where('diagnosis.date', fn (string $date) => str_ends_with($date, 'PKT'))
            ->has('diagnosis.findings', count($diagnosis->findings)));

    $content = (string) $this->actingAs($user)->get(dxShowUrl($diagnosis))->getContent();
    expect($content)->not->toContain('Blood Test');
});

test('new diagnosis copy has no em dash and never says blood test', function () {
    $lines = collect(trans('entry'))->filter(fn ($value, $key) => str_starts_with((string) $key, 'diagnosis_'));

    expect($lines->count())->toBeGreaterThan(40);

    foreach ($lines as $key => $line) {
        expect($line)->not->toContain('—', $key)
            ->and(strtolower((string) $line))->not->toContain('blood test', $key);
    }

    foreach (['create.tsx', 'show.tsx'] as $file) {
        expect((string) file_get_contents(resource_path('js/pages/patients/diagnoses/'.$file)))->not->toContain('—');
    }
});

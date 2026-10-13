<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiagnosisRequest;
use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Diagnosis\DiagnosisEngine;
use App\Support\Diagnosis\DiagnosisImageStore;
use App\Support\Diagnosis\DiagnosisPresenter;
use App\Support\Diagnosis\DiagnosisRequest;
use App\Support\EntryDate;
use App\Support\Patients\PatientAccess;
use App\Support\Patients\PatientPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Simulated AI diagnosis demo on a patient record (features.ai_demo). The
 * same people as the patient record (facility staff, frontline workers
 * included, and TB-AI admins); everyone else gets 404. The result comes from
 * the bound DiagnosisEngine, which today is the simulated one: every page
 * says so. The audit log never gets patient identifiers or the free text.
 */
class DiagnosisController extends Controller
{
    public function __construct(
        private readonly PatientAccess $access,
        private readonly PatientPresenter $patients,
        private readonly DiagnosisPresenter $presenter,
    ) {}

    public function create(Request $request, Team $current_team, Patient $patient): Response
    {
        $this->owned($this->user($request), $current_team, $patient);

        return Inertia::render('patients/diagnoses/create', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'patient' => $this->patients->details($patient),
            'testType' => Diagnosis::TEST_TB_ZN_SMEAR,
            'samples' => collect(DiagnosisImageStore::sampleKeys())->map(fn (string $key): array => [
                'key' => $key,
                'label' => __('entry.diagnosis_sample_label', ['key' => $key]),
                'url' => asset((string) config('tbai.diagnosis.samples.'.$key)),
            ])->values()->all(),
            'maxMegabytes' => (int) round((int) config('tbai.diagnosis.max_kilobytes', 10240) / 1024),
            'storeUrl' => route('patients.diagnoses.store', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'backUrl' => route('patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
        ]);
    }

    public function store(StoreDiagnosisRequest $request, Team $current_team, Patient $patient, DiagnosisImageStore $images, DiagnosisEngine $engine, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        $uuid = (string) Str::uuid();
        $source = (string) $request->validated('image_source');
        $testType = (string) $request->validated('test_type');

        $stored = $source === 'sample'
            ? $images->storeSample($patient->facility_id, $uuid, (string) $request->validated('sample'))
            : $images->storeUpload($patient->facility_id, $uuid, $this->upload($request));

        /** @var string|null $symptoms */
        $symptoms = $request->validated('symptoms');
        /** @var string|null $notes */
        $notes = $request->validated('notes');

        $result = $engine->analyse(new DiagnosisRequest(
            reference: $uuid,
            testType: $testType,
            disk: DiagnosisImageStore::disk(),
            imagePath: $stored['path'],
            symptoms: $symptoms,
            notes: $notes,
        ));

        $diagnosis = Diagnosis::query()->create([
            'uuid' => $uuid,
            'patient_id' => $patient->id,
            'facility_id' => $patient->facility_id,
            'test_type' => $testType,
            'image_source' => $source,
            'image_disk' => DiagnosisImageStore::disk(),
            'image_path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'],
            'mime_type' => $stored['mime_type'],
            'symptoms' => $symptoms,
            'notes' => $notes,
            'result' => $result->result,
            'grade' => $result->grade,
            'findings' => $result->findingsArray(),
            'engine' => $result->engine,
            'engine_version' => $result->engineVersion,
            'simulated' => $result->simulated,
            'created_by' => $user->id,
            'analysed_at' => now(),
        ]);

        $audit->write($user, 'diagnosis.created', $diagnosis, [
            'facility_id' => $patient->facility_id,
            'test_type' => $testType,
            'image_source' => $source,
            'engine' => $result->engine,
            'simulated' => $result->simulated,
        ]);

        // The wizard stays on screen to finish its processing animation, then
        // opens this URL. Without JavaScript the user lands back on the form.
        Inertia::flash('diagnosisUrl', route('patients.diagnoses.show', [
            'current_team' => $current_team->slug,
            'patient' => $patient->uuid,
            'diagnosis' => $diagnosis->uuid,
        ]));

        return redirect()->route('patients.diagnoses.create', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]);
    }

    public function show(Request $request, Team $current_team, Patient $patient, Diagnosis $diagnosis, AuditLogger $audit): Response
    {
        $user = $this->user($request);
        $this->ownedDiagnosis($user, $current_team, $patient, $diagnosis);

        $audit->write($user, 'diagnosis.viewed', $diagnosis);

        return Inertia::render('patients/diagnoses/show', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'title' => __('entry.diagnosis_report_title'),
            'banner' => __('entry.diagnosis_demo_banner'),
            'gradeNote' => __('entry.diagnosis_grade_note'),
            'patient' => $this->patients->details($patient),
            'diagnosis' => $this->presenter->report($diagnosis, $current_team),
            'backUrl' => route('patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'newUrl' => route('patients.diagnoses.create', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
        ]);
    }

    public function image(Request $request, Team $current_team, Patient $patient, Diagnosis $diagnosis): StreamedResponse
    {
        $this->ownedDiagnosis($this->user($request), $current_team, $patient, $diagnosis);

        return $this->stream($diagnosis, $diagnosis->image_path, $diagnosis->mime_type);
    }

    public function thumbnail(Request $request, Team $current_team, Patient $patient, Diagnosis $diagnosis): StreamedResponse
    {
        $this->ownedDiagnosis($this->user($request), $current_team, $patient, $diagnosis);
        abort_if($diagnosis->thumbnail_path === null, 404);

        return $this->stream($diagnosis, $diagnosis->thumbnail_path, 'image/jpeg');
    }

    private function stream(Diagnosis $diagnosis, string $path, string $mime): StreamedResponse
    {
        $disk = Storage::disk($diagnosis->image_disk);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'diagnosis-'.$diagnosis->uuid.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], 'inline');
    }

    private function upload(StoreDiagnosisRequest $request): UploadedFile
    {
        $file = $request->file('image');
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    /**
     * 404 unless this patient belongs to the facility in the URL and the user may see it.
     */
    private function owned(User $user, Team $team, Patient $patient): void
    {
        $facility = $this->access->facility($user, $team);
        abort_unless($patient->facility_id === $facility->id, 404);
    }

    /**
     * As owned(), and the diagnosis belongs to the patient. The policy check
     * repeats the rule on the record itself.
     */
    private function ownedDiagnosis(User $user, Team $team, Patient $patient, Diagnosis $diagnosis): void
    {
        $this->owned($user, $team, $patient);
        abort_unless($diagnosis->patient_id === $patient->id, 404);

        $diagnosis->setRelation('patient', $patient);
        Gate::forUser($user)->authorize('view', $diagnosis);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}

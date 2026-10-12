<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePatientRequest;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\SuspectAssessment;
use App\Models\Team;
use App\Models\Treatment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\EntryDate;
use App\Support\Patients\PatientAccess;
use App\Support\Patients\PatientIdentifiers;
use App\Support\Patients\PatientPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Patient records at one facility: list, create, view, edit. Facility staff
 * (frontline workers included) and TB-AI admins only; everyone else gets
 * 404. Names, CNICs and phones are decrypted only for those people and are
 * never written to the audit log.
 */
class PatientController extends Controller
{
    public function __construct(
        private readonly PatientAccess $access,
        private readonly PatientPresenter $presenter,
    ) {}

    public function index(Request $request, Team $current_team): Response
    {
        $facility = $this->access->facility($request->user(), $current_team);
        $search = trim($request->string('q')->toString());
        $today = EntryDate::now()->toDateString();

        $query = Patient::query()
            ->where('facility_id', $facility->id)
            ->with(['activeTreatment.visits', 'treatments'])
            ->latest('id');

        if ($search !== '') {
            $digits = PatientIdentifiers::cnicDigits($search);

            $query->where(function ($where) use ($search, $digits): void {
                $where->where('register_number', $search);

                if ($digits !== null) {
                    $where->orWhere('cnic_hash', PatientIdentifiers::cnicHash($digits));
                }
            });
        }

        $page = $query->simplePaginate(25)->withQueryString();

        return Inertia::render('patients/index', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'search' => $search,
            'searchUrl' => route('patients.index', ['current_team' => $current_team->slug]),
            'newUrl' => route('patients.create', ['current_team' => $current_team->slug]),
            'previousUrl' => $page->previousPageUrl(),
            'nextUrl' => $page->nextPageUrl(),
            'rows' => collect($page->items())->map(fn (Patient $patient): array => [
                'id' => $patient->uuid,
                'name' => $patient->name,
                'registerNumber' => $patient->register_number,
                'age' => $patient->age,
                'genderLabel' => $patient->gender->label(),
                ...$this->presenter->listStatus($patient, $today),
                'href' => route('patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            ])->values()->all(),
        ]);
    }

    public function create(Request $request, Team $current_team): Response
    {
        $facility = $this->access->facility($request->user(), $current_team);
        $screening = $this->screening($facility, $request->string('screening')->toString());

        return Inertia::render('patients/form', [
            ...$this->formProps($current_team),
            'mode' => 'create',
            'action' => route('patients.store', ['current_team' => $current_team->slug]),
            'method' => 'post',
            'backUrl' => $screening === null
                ? route('patients.index', ['current_team' => $current_team->slug])
                : route('suspect.show', ['current_team' => $current_team->slug, 'assessment' => $screening->uuid]),
            'screening' => $screening?->uuid,
            'patient' => [
                'name' => $screening === null ? '' : ($screening->patient_name ?? ''),
                'cnic' => '',
                'phone' => '',
                'gender' => $screening !== null && in_array($screening->sex, ['female', 'male', 'other'], true) ? $screening->sex : '',
                'age' => '',
                'registerNumber' => $screening === null ? '' : ($screening->register_number ?? ''),
                'informedAt' => null,
            ],
        ]);
    }

    public function store(SavePatientRequest $request, Team $current_team, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        $facility = $request->facility();
        $screening = $this->screening($facility, (string) $request->validated('screening'));

        $patient = Patient::query()->create([
            ...$request->patientAttributes(),
            'facility_id' => $facility->id,
            'informed_of_data_use' => true,
            'informed_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if ($screening !== null) {
            $screening->update(['patient_id' => $patient->id]);
        }

        $audit->write($user, 'patient.created', $patient, [
            'facility_id' => $facility->id,
            'screening' => $screening?->uuid,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.patients_created')]);

        return redirect()->route('patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]);
    }

    public function show(Request $request, Team $current_team, Patient $patient, AuditLogger $audit): Response
    {
        $user = $this->user($request);
        $this->owned($user, $current_team, $patient);
        $today = EntryDate::now()->toDateString();

        $treatments = $patient->treatments()->with('visits')->orderByDesc('start_date')->orderByDesc('id')->get();
        $active = $treatments->first(fn (Treatment $treatment): bool => $treatment->isActive());

        $audit->write($user, 'patient.viewed', $patient);

        return Inertia::render('patients/show', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'today' => $today,
            'patient' => $this->presenter->details($patient),
            'screenings' => $patient->screenings()->latest('created_at')->latest('id')->get()
                ->map(fn (SuspectAssessment $assessment): array => $this->presenter->screening($assessment, $current_team))
                ->values()->all(),
            'activeTreatment' => $active === null ? null : $this->presenter->treatment($active, $patient, $current_team, $today),
            'pastTreatments' => $treatments
                ->reject(fn (Treatment $treatment): bool => $treatment->isActive())
                ->map(fn (Treatment $treatment): array => $this->presenter->treatment($treatment, $patient, $current_team, $today))
                ->values()->all(),
            'options' => $this->presenter->options(),
            'startTreatmentUrl' => route('patients.treatments.store', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'editUrl' => route('patients.edit', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'indexUrl' => route('patients.index', ['current_team' => $current_team->slug]),
        ]);
    }

    public function edit(Request $request, Team $current_team, Patient $patient): Response
    {
        $this->owned($this->user($request), $current_team, $patient);

        return Inertia::render('patients/form', [
            ...$this->formProps($current_team),
            'mode' => 'edit',
            'action' => route('patients.update', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'method' => 'put',
            'backUrl' => route('patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'screening' => null,
            'patient' => [
                'name' => $patient->name,
                'cnic' => PatientIdentifiers::formatCnic($patient->cnic) ?? '',
                'phone' => $patient->phone ?? '',
                'gender' => $patient->gender->value,
                'age' => (string) $patient->age,
                'registerNumber' => $patient->register_number,
                'informedAt' => $patient->informed_at === null ? null : EntryDate::dateTime($patient->informed_at),
            ],
        ]);
    }

    public function update(SavePatientRequest $request, Team $current_team, Patient $patient, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        $this->owned($user, $current_team, $patient);

        $patient->fill($request->patientAttributes());
        $fields = array_values(array_diff(array_keys($patient->getDirty()), ['cnic_hash']));

        if ($fields !== []) {
            $patient->updated_by = $user->id;
            $patient->save();

            $audit->write($user, 'patient.updated', $patient, ['fields' => $fields]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.patients_saved')]);

        return redirect()->route('patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Team $team): array
    {
        return [
            'facilityName' => $team->name,
            'dateLabel' => EntryDate::label(),
            'genders' => $this->presenter->options()['genders'],
        ];
    }

    /**
     * A screening at this facility, by uuid, or null.
     */
    private function screening(Facility $facility, string $uuid): ?SuspectAssessment
    {
        if ($uuid === '') {
            return null;
        }

        return SuspectAssessment::query()
            ->where('uuid', $uuid)
            ->where('facility_id', $facility->id)
            ->first();
    }

    /**
     * 404 unless this patient belongs to the facility in the URL and the user may see it.
     */
    private function owned(User $user, Team $team, Patient $patient): void
    {
        $facility = $this->access->facility($user, $team);
        abort_unless($patient->facility_id === $facility->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}

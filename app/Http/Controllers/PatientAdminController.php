<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Patient;
use App\Support\EntryDate;
use App\Support\Patients\PatientPresenter;
use App\Support\ResourceResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

/**
 * Patients across facilities, for TB-AI admins only. No CSV export: the
 * rows carry decrypted names.
 */
class PatientAdminController extends Controller
{
    public function index(Request $request, PatientPresenter $presenter): Response
    {
        Gate::authorize('administer');

        $query = Patient::query()
            ->with(['facility.team', 'activeTreatment.visits', 'treatments'])
            ->orderByDesc('id');

        $facilityId = $request->integer('facility');

        if ($facilityId > 0) {
            $query->where('facility_id', $facilityId);
        }

        $columns = [
            ['key' => 'facility', 'label' => __('entry.column_facility')],
            ['key' => 'register_number', 'label' => __('entry.patients_register_number')],
            ['key' => 'name', 'label' => __('entry.patients_name')],
            ['key' => 'age', 'label' => __('entry.patients_age')],
            ['key' => 'gender', 'label' => __('entry.patients_gender')],
            ['key' => 'status', 'label' => __('entry.patients_treatment')],
        ];

        $today = EntryDate::now()->toDateString();
        $page = $query->paginate(20)->withQueryString();
        $facilities = Facility::query()->with('team')->get()->sortBy(
            fn (Facility $facility): string => mb_strtolower($facility->team->name),
        );

        return ResourceResponse::page(
            title: __('entry.nav_patients'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (Patient $patient): array => [
                'id' => $patient->uuid,
                'href' => route('patients.show', ['current_team' => $patient->facility->team->slug, 'patient' => $patient->uuid]),
                'cells' => [
                    'facility' => $patient->facility->team->name,
                    'register_number' => $patient->register_number,
                    'name' => $patient->name,
                    'age' => (string) $patient->age,
                    'gender' => $patient->gender->label(),
                    'status' => $presenter->listStatus($patient, $today)['status'],
                ],
                'controls' => [],
            ])->values()->all(),
            filters: [[
                'key' => 'facility',
                'label' => __('entry.column_facility'),
                'type' => 'select',
                'value' => $facilityId > 0 ? (string) $facilityId : '',
                'options' => array_merge(
                    [['value' => '', 'label' => __('entry.any')]],
                    $facilities->map(fn (Facility $facility): array => [
                        'value' => (string) $facility->id,
                        'label' => $facility->team->name,
                    ])->values()->all(),
                ),
            ]],
            sort: 'id',
            direction: 'desc',
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: null,
            shell: [
                'title' => __('entry.nav_patients'),
                'description' => __('entry.patients_admin_description'),
                'empty' => [
                    'title' => __('entry.nav_patients'),
                    'body' => __('entry.patients_admin_empty'),
                ],
            ],
        );
    }
}

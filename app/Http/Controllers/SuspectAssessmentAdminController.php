<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\SuspectAssessment;
use App\Support\AdminTabs;
use App\Support\EntryDate;
use App\Support\ResourceResponse;
use App\Support\Suspect\AssessmentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuspectAssessmentAdminController extends Controller
{
    /**
     * Facility, date, age band, sex, score, priority and referral only.
     * No patient identifiers exist to show.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        $query = SuspectAssessment::query()
            ->with(['facility.team', 'ruleSet'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $facilityId = $request->integer('facility');

        if ($facilityId > 0) {
            $query->where('facility_id', $facilityId);
        }

        $refer = $request->string('refer')->toString();

        if ($refer === '1' || $refer === '0') {
            $query->where('status', SuspectAssessment::STATUS_COMPLETED)
                ->where('refer_for_testing', $refer === '1');
        }

        $columns = [
            ['key' => 'facility', 'label' => __('entry.column_facility')],
            ['key' => 'date', 'label' => __('entry.column_date')],
            ['key' => 'age_band', 'label' => __('entry.suspect_age_band')],
            ['key' => 'sex', 'label' => __('entry.suspect_sex')],
            ['key' => 'score', 'label' => __('entry.column_score')],
            ['key' => 'priority', 'label' => __('entry.suspect_priority')],
            ['key' => 'refer', 'label' => __('entry.suspect_column_refer')],
            ['key' => 'rule_set', 'label' => __('entry.suspect_column_version')],
        ];

        $cells = fn (SuspectAssessment $assessment): array => [
            'facility' => $assessment->facility->team->name,
            'date' => $assessment->created_at === null ? '' : EntryDate::dateTime($assessment->created_at),
            'age_band' => AssessmentPresenter::optionLabel($assessment->age_band),
            'sex' => AssessmentPresenter::optionLabel($assessment->sex),
            'score' => $assessment->isCompleted() ? (string) $assessment->score : __('entry.suspect_in_progress'),
            'priority' => $assessment->priority === null ? '' : ResourceResponse::line('entry.suspect_priority_'.$assessment->priority),
            'refer' => ! $assessment->isCompleted() ? '' : ($assessment->refer_for_testing ? __('entry.yes') : __('entry.no')),
            'rule_set' => $assessment->ruleSet->version,
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'suspect-screenings.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (SuspectAssessment $assessment): array => array_values($cells($assessment))),
            );
        }

        $page = $query->paginate(20)->withQueryString();
        $facilities = Facility::query()->with('team')->get()->sortBy(
            fn (Facility $facility): string => mb_strtolower($facility->team->name),
        );

        return ResourceResponse::page(
            title: __('entry.nav_suspect_assessments'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (SuspectAssessment $assessment): array => [
                'id' => $assessment->uuid,
                'href' => route('suspect.show', ['current_team' => $assessment->facility->team->slug, 'assessment' => $assessment->uuid]),
                'cells' => $cells($assessment),
                'controls' => [],
            ])->values()->all(),
            filters: [
                [
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
                ],
                [
                    'key' => 'refer',
                    'label' => __('entry.suspect_column_refer'),
                    'type' => 'select',
                    'value' => $refer === '1' || $refer === '0' ? $refer : '',
                    'options' => [
                        ['value' => '', 'label' => __('entry.any')],
                        ['value' => '1', 'label' => __('entry.yes')],
                        ['value' => '0', 'label' => __('entry.no')],
                    ],
                ],
            ],
            sort: 'date',
            direction: 'desc',
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
            shell: [
                'title' => __('entry.nav_suspect_rules'),
                'description' => __('entry.suspect_assessments_description'),
                'tabs' => AdminTabs::suspect('assessments'),
                'empty' => [
                    'title' => __('entry.nav_suspect_assessments'),
                    'body' => __('entry.suspect_assessments_empty'),
                ],
            ],
        );
    }
}

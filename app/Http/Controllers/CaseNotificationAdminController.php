<?php

namespace App\Http\Controllers;

use App\Models\CaseNotification;
use App\Models\Facility;
use App\Models\User;
use App\Support\AdminTabs;
use App\Support\AuditLogger;
use App\Support\CommandCache;
use App\Support\ResourceResponse;
use App\Support\UploadedCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseNotificationAdminController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort(
            $request,
            ['facility', 'month', 'new_cases', 'relapse_cases'],
            'month',
            'desc',
        );

        $month = trim($request->string('month')->toString());

        $query = CaseNotification::query()->with('facility.team');

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $query->where('month', $month);
        }

        $query->orderBy($sort === 'facility' ? 'facility_id' : $sort, $direction)
            ->orderBy('id', $direction);

        $columns = [
            ['key' => 'facility', 'label' => __('entry.column_facility')],
            ['key' => 'month', 'label' => __('entry.column_month')],
            ['key' => 'new_cases', 'label' => __('entry.column_new_cases')],
            ['key' => 'relapse_cases', 'label' => __('entry.column_relapse_cases')],
            ['key' => 'other_previously_treated', 'label' => __('entry.column_other_previously_treated')],
            ['key' => 'history_unknown', 'label' => __('entry.column_history_unknown')],
            ['key' => 'notified', 'label' => __('entry.column_notified')],
            ['key' => 'source', 'label' => __('entry.column_source')],
        ];
        $cells = fn (CaseNotification $row): array => [
            'facility' => $row->facility->team->name ?? (string) $row->facility_id,
            'month' => $row->month,
            'new_cases' => (string) $row->new_cases,
            'relapse_cases' => (string) $row->relapse_cases,
            'other_previously_treated' => (string) $row->other_previously_treated,
            'history_unknown' => (string) $row->history_unknown,
            'notified' => (string) $row->countedCases(),
            'source' => $row->source,
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'case-notifications.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (CaseNotification $row): array => array_values($cells($row))),
            );
        }

        $page = $query->paginate(20)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_case_notifications'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (CaseNotification $row): array => [
                'id' => (string) $row->id,
                'href' => null,
                'cells' => $cells($row),
                'controls' => [],
            ])->values()->all(),
            filters: [
                [
                    'key' => 'month',
                    'label' => __('entry.column_month'),
                    'type' => 'search',
                    'value' => $month,
                ],
            ],
            sort: $sort,
            direction: $direction,
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: route('admin.case-notifications.import-form'),
            createLabel: __('entry.nav_case_notifications').' CSV',
            exportUrl: ResourceResponse::exportUrl($request),
            shell: [
                'title' => __('entry.nav_kpis'),
                'description' => __('entry.case_notifications_description'),
                'tabs' => AdminTabs::kpis('case_notifications'),
                'empty' => [
                    'title' => __('entry.nav_case_notifications'),
                    'body' => __('entry.case_notifications_description'),
                ],
            ],
        );
    }

    public function importForm(): Response
    {
        Gate::authorize('administer');

        return Inertia::render('resources/import', [
            'title' => __('entry.case_notification_import_title'),
            'heading' => __('entry.nav_kpis'),
            'description' => __('entry.case_notification_import_description'),
            'tabs' => AdminTabs::kpis('case_notifications'),
            'action' => route('admin.case-notifications.import'),
            'backUrl' => route('admin.case-notifications.index'),
            'columns' => ['facility_code', 'month', 'new_cases', 'relapse_cases', 'other_previously_treated', 'history_unknown', 'source'],
            'sample' => "facility_code,month,new_cases,relapse_cases,other_previously_treated,history_unknown,source\n".
                "BMU-001,2025-10,12,3,1,0,NTMIS\n".
                'BMU-002,2025-10,8,2,0,1,NTMIS',
        ]);
    }

    public function import(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('administer');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $rows = UploadedCsv::read($request->file('file'));

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('entry.no_rows'),
            ]);
        }

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $codes = array_unique(array_filter(array_map(
            fn (array $fields): string => trim($fields['facility_code'] ?? ''),
            $rows,
        )));
        $facilityIds = Facility::query()
            ->whereIn('code', $codes)
            ->pluck('id', 'code');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $facilityIds, &$created, &$updated, &$skipped): void {
            foreach ($rows as $fields) {
                $parsed = $this->parseRow($fields, $facilityIds);

                if ($parsed === null) {
                    $skipped++;

                    continue;
                }

                $affected = CaseNotification::query()->updateOrCreate(
                    [
                        'facility_id' => $parsed['facility_id'],
                        'month' => $parsed['month'],
                    ],
                    [
                        'new_cases' => $parsed['new_cases'],
                        'relapse_cases' => $parsed['relapse_cases'],
                        'other_previously_treated' => $parsed['other_previously_treated'],
                        'history_unknown' => $parsed['history_unknown'],
                        'source' => $parsed['source'],
                    ],
                );

                if ($affected->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        CommandCache::bump();

        $audit->write($actor, 'case_notifications.imported', null, [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return back()->with('status', __('entry.import_status', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]));
    }

    /**
     * @param  array<string, string>  $fields
     * @param  Illuminate\Support\Collection<string, int>  $facilityIds
     * @return array{facility_id: int, month: string, new_cases: int, relapse_cases: int, other_previously_treated: int, history_unknown: int, source: string}|null
     */
    private function parseRow(array $fields, $facilityIds): ?array
    {
        $code = trim($fields['facility_code'] ?? '');

        if (! isset($facilityIds[$code])) {
            return null;
        }

        $month = trim($fields['month'] ?? '');

        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return null;
        }

        $newCases = filter_var($fields['new_cases'] ?? '', FILTER_VALIDATE_INT);
        $relapse = filter_var($fields['relapse_cases'] ?? '', FILTER_VALIDATE_INT);
        $other = filter_var($fields['other_previously_treated'] ?? '', FILTER_VALIDATE_INT);
        $unknown = filter_var($fields['history_unknown'] ?? '', FILTER_VALIDATE_INT);

        if ($newCases === false
            || $relapse === false
            || $other === false
            || $unknown === false
            || $newCases < 0
            || $relapse < 0
            || $other < 0
            || $unknown < 0) {
            return null;
        }

        return [
            'facility_id' => (int) $facilityIds[$code],
            'month' => $month,
            'new_cases' => (int) $newCases,
            'relapse_cases' => (int) $relapse,
            'other_previously_treated' => (int) $other,
            'history_unknown' => (int) $unknown,
            'source' => trim($fields['source'] ?? '') !== '' ? trim($fields['source'] ?? '') : 'CSV import',
        ];
    }
}

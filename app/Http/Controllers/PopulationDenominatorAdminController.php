<?php

namespace App\Http\Controllers;

use App\Enums\AreaType;
use App\Enums\PopulationStatus;
use App\Models\City;
use App\Models\PopulationDenominator;
use App\Models\Province;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PopulationDenominatorAdminController extends Controller
{
    /**
     * The official census the seeded figures come from. Blank cells in a CSV
     * inherit these so provenance is never silent.
     */
    private const DEFAULT_SOURCE = 'PBS Census 2023';

    private const DEFAULT_SOURCE_YEAR = 2023;

    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort(
            $request,
            ['area', 'year', 'population', 'source', 'status'],
            'year',
            'desc',
        );

        $type = $request->string('type')->toString();
        $year = trim($request->string('year')->toString());

        $query = PopulationDenominator::query();

        if (AreaType::tryFrom($type) !== null) {
            $query->where('area_type', AreaType::from($type));
        }

        if ($year !== '') {
            $query->where('year', $year);
        }

        $query->orderBy($sort === 'area' ? 'area_type' : $sort, $direction)
            ->orderBy('area_id', $direction)
            ->orderBy('year', $direction);

        $cityNames = City::query()->pluck('name', 'id');
        $provinceNames = Province::query()->pluck('name', 'id');
        $columns = [
            ['key' => 'area_type', 'label' => __('entry.column_area_type')],
            ['key' => 'area', 'label' => __('entry.column_area')],
            ['key' => 'year', 'label' => __('entry.column_year')],
            ['key' => 'status', 'label' => __('entry.column_status')],
            ['key' => 'population', 'label' => __('entry.column_population')],
            ['key' => 'source', 'label' => __('entry.column_source')],
            ['key' => 'source_year', 'label' => __('entry.column_source_year')],
            ['key' => 'method_note', 'label' => __('entry.column_method_note')],
        ];
        $cells = fn (PopulationDenominator $row): array => [
            'area_type' => $row->area_type->label(),
            'area' => $this->areaName($row, $cityNames, $provinceNames),
            'year' => (string) $row->year,
            'status' => $row->status->label(),
            'population' => $row->population === null ? '—' : number_format($row->population),
            'source' => $row->source,
            'source_year' => $row->source_year === null ? '' : (string) $row->source_year,
            'method_note' => $row->method_note ?? '',
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'population-denominators.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (PopulationDenominator $row): array => array_values($cells($row))),
            );
        }

        $page = $query->paginate(20)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_population_denominators'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (PopulationDenominator $row): array => [
                'id' => (string) $row->id,
                'href' => null,
                'cells' => $cells($row),
                'controls' => [[
                    'kind' => 'link',
                    'url' => route('admin.population-denominators.edit', $row),
                    'label' => $row->population === null
                        ? __('entry.population_set_figure')
                        : __('entry.population_edit'),
                ]],
            ])->values()->all(),
            filters: [
                [
                    'key' => 'type',
                    'label' => __('entry.column_area_type'),
                    'type' => 'select',
                    'value' => $type,
                    'options' => [
                        ['value' => '', 'label' => __('entry.scope_all')],
                        ...AreaType::options(),
                    ],
                ],
                [
                    'key' => 'year',
                    'label' => __('entry.column_year'),
                    'type' => 'search',
                    'value' => $year,
                ],
            ],
            sort: $sort,
            direction: $direction,
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: route('admin.population-denominators.import-form'),
            createLabel: __('entry.population_import_label'),
            exportUrl: ResourceResponse::exportUrl($request),
            shell: [
                'title' => __('entry.nav_kpis'),
                'description' => __('entry.population_denominators_description'),
                'tabs' => AdminTabs::kpis('population_denominators'),
                'empty' => [
                    'title' => __('entry.nav_population_denominators'),
                    'body' => __('entry.population_denominators_description'),
                ],
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize('administer');

        return Inertia::render('resources/population-form', [
            'title' => __('entry.population_create_title'),
            'heading' => __('entry.nav_kpis'),
            'description' => __('entry.population_create_description'),
            'tabs' => AdminTabs::kpis('population_denominators'),
            'mode' => 'create',
            'locked' => null,
            'action' => route('admin.population-denominators.store'),
            'method' => 'post',
            'backUrl' => route('admin.population-denominators.index'),
            'importUrl' => route('admin.population-denominators.import-form'),
            'areas' => $this->areaOptions(),
            'row' => null,
        ]);
    }

    public function edit(PopulationDenominator $populationDenominator): Response
    {
        Gate::authorize('administer');

        return Inertia::render('resources/population-form', [
            'title' => __('entry.population_edit_title'),
            'heading' => __('entry.nav_kpis'),
            'description' => __('entry.population_denominators_description'),
            'tabs' => AdminTabs::kpis('population_denominators'),
            'mode' => 'edit',
            'locked' => $this->locked($populationDenominator),
            'action' => route('admin.population-denominators.update', $populationDenominator),
            'method' => 'patch',
            'backUrl' => route('admin.population-denominators.index'),
            'importUrl' => route('admin.population-denominators.import-form'),
            'areas' => [],
            'row' => [
                'area' => $this->areaName(
                    $populationDenominator,
                    City::query()->pluck('name', 'id'),
                    Province::query()->pluck('name', 'id'),
                ),
                'year' => $populationDenominator->year,
                'population' => $populationDenominator->population,
                'status' => $populationDenominator->status->value,
                'source' => $populationDenominator->source,
                'source_year' => $populationDenominator->source_year,
                'reference_url' => $populationDenominator->reference_url,
                'method_note' => $populationDenominator->method_note,
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('administer');

        $data = $this->validated($request, requireArea: true);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $affected = PopulationDenominator::query()->updateOrCreate(
            [
                'area_type' => $data['area_type'],
                'area_id' => $data['area_id'],
                'year' => $data['year'],
            ],
            [
                ...$this->populationFields($data),
                'entered_by' => $actor->id,
            ],
        );

        $audit->write($actor, 'population_denominators.created', $affected, [
            'area_type' => $affected->area_type->value,
            'area_id' => $affected->area_id,
            'year' => $affected->year,
            'population' => $affected->population,
            'status' => $affected->status->value,
            'source' => $affected->source,
        ]);

        return redirect()->route('admin.population-denominators.index')
            ->with('status', __('entry.population_saved'));
    }

    public function update(
        Request $request,
        PopulationDenominator $populationDenominator,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('administer');

        $data = $this->validated($request, requireArea: false);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $populationDenominator->fill($this->populationFields($data));
        $populationDenominator->entered_by = $actor->id;
        $populationDenominator->save();

        $audit->write($actor, 'population_denominators.updated', $populationDenominator, [
            'area_type' => $populationDenominator->area_type->value,
            'area_id' => $populationDenominator->area_id,
            'year' => $populationDenominator->year,
            'population' => $populationDenominator->population,
            'status' => $populationDenominator->status->value,
            'source' => $populationDenominator->source,
        ]);

        return redirect()->route('admin.population-denominators.index')
            ->with('status', __('entry.population_saved'));
    }

    public function importForm(): Response
    {
        Gate::authorize('administer');

        return Inertia::render('resources/import', [
            'title' => __('entry.population_import_title'),
            'heading' => __('entry.nav_kpis'),
            'description' => __('entry.population_import_description'),
            'tabs' => AdminTabs::kpis('population_denominators'),
            'action' => route('admin.population-denominators.import'),
            'backUrl' => route('admin.population-denominators.index'),
            'columns' => ['area_type', 'area_id', 'year', 'population', 'source', 'source_year', 'method_note', 'reference_url'],
            'sample' => "area_type,area_id,year,population,source,source_year,method_note,reference_url\n".
                "district,5,2025,1250000,PBS Census 2023,2023,Sindh Health Survey,https://www.pbs.gov.pk/wp-content/uploads/census_tables/tables/table_1_sindh_districts.pdf\n".
                'province,2,2025,,PBS Census 2023,2023,,https://www.pbs.gov.pk/wp-content/uploads/census_tables/tables/table_1_sindh_districts.pdf',
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

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $actor, &$created, &$updated, &$skipped): void {
            foreach ($rows as $index => $fields) {
                $parsed = $this->parseRow($fields, $index + 1);

                if ($parsed === null) {
                    $skipped++;

                    continue;
                }

                $affected = PopulationDenominator::query()->updateOrCreate(
                    [
                        'area_type' => $parsed['area_type'],
                        'area_id' => $parsed['area_id'],
                        'year' => $parsed['year'],
                    ],
                    [
                        ...$parsed['fields'],
                        'entered_by' => $actor->id,
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

        $audit->write($actor, 'population_denominators.imported', null, [
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
     * Shared field list for the single-row create and edit forms. For a create,
     * the area and year are the record's key and are required.
     *
     * @return array{
     *     area_type: AreaType,
     *     area_id: int,
     *     year: int,
     *     population: int|null,
     *     status: PopulationStatus,
     *     source: string,
     *     source_year: int|null,
     *     reference_url: string|null,
     *     method_note: string|null
     * }
     */
    private function validated(Request $request, bool $requireArea): array
    {
        $rules = [
            'population' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_year' => ['nullable', 'integer', 'between:1900,2100'],
            'reference_url' => ['nullable', 'url', 'max:1024'],
            'method_note' => ['nullable', 'string', 'max:1000'],
        ];

        if ($requireArea) {
            $rules['area_type'] = ['required', Rule::enum(AreaType::class)];
            $rules['area_id'] = ['required', 'integer', 'min:1'];
            $rules['year'] = ['required', 'integer', 'between:1900,2100'];
        }

        $data = $request->validate($rules);

        if ($requireArea) {
            $type = AreaType::from($data['area_type']);
            $areaId = (int) $data['area_id'];
            $exists = $type === AreaType::District
                ? City::query()->whereKey($areaId)->exists()
                : Province::query()->whereKey($areaId)->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'area_id' => __('entry.population_area_unknown'),
                ]);
            }
        } else {
            $type = null;
            $areaId = null;
        }

        return [
            'area_type' => $type,
            'area_id' => $areaId,
            'year' => $requireArea ? (int) $data['year'] : 0,
            ...$this->populationFields($data),
        ];
    }

    /**
     * Normalises the population fields for a create or update. A blank source
     * inherits the census defaults; a blank population marks the row as a
     * placeholder waiting for a census figure, and any figure at all confirms
     * the row.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function populationFields(array $data): array
    {
        $population = array_key_exists('population', $data)
            ? ($data['population'] === null || $data['population'] === '' ? null : (int) $data['population'])
            : null;

        $source = trim((string) ($data['source'] ?? ''));

        $sourceYear = isset($data['source_year']) && $data['source_year'] !== '' && $data['source_year'] !== null
            ? (int) $data['source_year']
            : self::DEFAULT_SOURCE_YEAR;

        $referenceUrl = trim((string) ($data['reference_url'] ?? ''));

        $methodNote = trim((string) ($data['method_note'] ?? ''));

        return [
            'population' => $population,
            'status' => $population !== null ? PopulationStatus::Confirmed : PopulationStatus::Placeholder,
            'source' => $source === '' ? self::DEFAULT_SOURCE : $source,
            'source_year' => $sourceYear,
            'reference_url' => $referenceUrl === '' ? null : $referenceUrl,
            'method_note' => $methodNote === '' ? null : $methodNote,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function areaOptions(): array
    {
        $provinces = Province::query()->orderBy('name')->get(['id', 'name']);
        $cities = City::query()->orderBy('name')->get(['id', 'name']);

        return [
            ...$provinces->map(fn (Province $province): array => [
                'value' => AreaType::Province->value.':'.$province->id,
                'label' => __('entry.area_option_province', ['name' => $province->name]),
            ]),
            ...$cities->map(fn (City $city): array => [
                'value' => AreaType::District->value.':'.$city->id,
                'label' => __('entry.area_option_district', ['name' => $city->name]),
            ]),
        ];
    }

    /**
     * The immutable key of an existing row, shown in the edit form.
     *
     * @return array{area_type: string, area_id: string, year: string}
     */
    private function locked(PopulationDenominator $row): array
    {
        return [
            'area_type' => $row->area_type->value,
            'area_id' => (string) $row->area_id,
            'year' => (string) $row->year,
        ];
    }

    /**
     * @param  Illuminate\Support\Collection<int, string>  $cityNames
     * @param  Illuminate\Support\Collection<int, string>  $provinceNames
     */
    private function areaName(PopulationDenominator $row, $cityNames, $provinceNames): string
    {
        return match ($row->area_type) {
            AreaType::District => $cityNames->get($row->area_id, (string) $row->area_id),
            AreaType::Province => $provinceNames->get($row->area_id, (string) $row->area_id),
        };
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{area_type: AreaType, area_id: int, year: int, fields: array<string, mixed>}|null
     */
    private function parseRow(array $fields, int $rowNumber): ?array
    {
        $type = AreaType::tryFrom($fields['area_type'] ?? '');
        $areaId = filter_var($fields['area_id'] ?? '', FILTER_VALIDATE_INT);
        $year = filter_var($fields['year'] ?? '', FILTER_VALIDATE_INT);

        if ($type === null
            || $areaId === false
            || $areaId < 1
            || $year === false
            || $year < 1900
            || $year > 2100) {
            return null;
        }

        if ($type === AreaType::District && ! City::query()->whereKey($areaId)->exists()) {
            return null;
        }

        if ($type === AreaType::Province && ! Province::query()->whereKey($areaId)->exists()) {
            return null;
        }

        $populationRaw = $fields['population'] ?? '';
        $population = $populationRaw === '' ? null : filter_var($populationRaw, FILTER_VALIDATE_INT);

        if ($population !== null && $population < 1) {
            throw ValidationException::withMessages([
                'file' => __('entry.population_import_bad_population', ['row' => $rowNumber]),
            ]);
        }

        return [
            'area_type' => $type,
            'area_id' => (int) $areaId,
            'year' => (int) $year,
            'fields' => $this->populationFields([
                'population' => $population,
                'source' => $fields['source'] ?? '',
                'source_year' => $fields['source_year'] ?? '',
                'reference_url' => $fields['reference_url'] ?? '',
                'method_note' => $fields['method_note'] ?? '',
            ]),
        ];
    }
}

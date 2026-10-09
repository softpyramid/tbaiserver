<?php

namespace App\Http\Controllers;

use App\Enums\AreaType;
use App\Models\City;
use App\Models\PopulationDenominator;
use App\Models\Province;
use App\Models\User;
use App\Support\AdminTabs;
use App\Support\AuditLogger;
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

class PopulationDenominatorAdminController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort(
            $request,
            ['area', 'year', 'population', 'source'],
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
            ['key' => 'population', 'label' => __('entry.column_population')],
            ['key' => 'source', 'label' => __('entry.column_source')],
            ['key' => 'source_year', 'label' => __('entry.column_source_year')],
            ['key' => 'method_note', 'label' => __('entry.column_method_note')],
        ];
        $cells = fn (PopulationDenominator $row): array => [
            'area_type' => $row->area_type->label(),
            'area' => $this->areaName($row, $cityNames, $provinceNames),
            'year' => (string) $row->year,
            'population' => number_format($row->population),
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
                'controls' => [],
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
            createLabel: __('entry.nav_population_denominators').' CSV',
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
            'columns' => ['area_type', 'area_id', 'year', 'population', 'source', 'source_year', 'method_note'],
            'sample' => "area_type,area_id,year,population,source,source_year,method_note\n".
                "district,5,2025,1250000,PSLM 2023-24,2023,Sindh Health Survey\n".
                'province,2,2025,48900000,PSLM 2023-24,2023,Sindh Health Survey',
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
            foreach ($rows as $fields) {
                $parsed = $this->parseRow($fields);

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
                        'population' => $parsed['population'],
                        'source' => $parsed['source'],
                        'source_year' => $parsed['source_year'],
                        'method_note' => $parsed['method_note'],
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
     * @return array{area_type: AreaType, area_id: int, year: int, population: int, source: string, source_year: int|null, method_note: string|null}|null
     */
    private function parseRow(array $fields): ?array
    {
        $type = AreaType::tryFrom($fields['area_type'] ?? '');
        $areaId = filter_var($fields['area_id'] ?? '', FILTER_VALIDATE_INT);
        $year = filter_var($fields['year'] ?? '', FILTER_VALIDATE_INT);
        $population = filter_var($fields['population'] ?? '', FILTER_VALIDATE_INT);
        $source = trim($fields['source'] ?? '');

        if ($type === null
            || $areaId === false
            || $areaId < 1
            || $year === false
            || $year < 1900
            || $year > 2100
            || $population === false
            || $population < 1
            || $source === '') {
            return null;
        }

        if ($type === AreaType::District && ! City::query()->whereKey($areaId)->exists()) {
            return null;
        }

        if ($type === AreaType::Province && ! Province::query()->whereKey($areaId)->exists()) {
            return null;
        }

        $sourceYear = filter_var($fields['source_year'] ?? '', FILTER_VALIDATE_INT);
        $methodNote = trim($fields['method_note'] ?? '');

        return [
            'area_type' => $type,
            'area_id' => (int) $areaId,
            'year' => (int) $year,
            'population' => (int) $population,
            'source' => $source,
            'source_year' => $sourceYear === false ? null : (int) $sourceYear,
            'method_note' => $methodNote === '' ? null : $methodNote,
        ];
    }
}

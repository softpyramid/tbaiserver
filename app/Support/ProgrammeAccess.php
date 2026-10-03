<?php

namespace App\Support;

use App\Models\ProgrammeAssignment;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammeAccess
{
    public function __construct(private DataScope $scope) {}

    /**
     * Viewer and officer scopes, with the people who can open them.
     * Facility records stay on the facilities list.
     */
    public function page(Request $request): Response|StreamedResponse
    {
        $search = trim($request->string('q')->toString());

        $query = ProgrammeAssignment::query()->with('user');

        if ($search !== '') {
            $like = ResourceResponse::like($search);
            $query->whereHas('user', function ($users) use ($like): void {
                $users->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        $assignments = $query->get()->sortBy([
            fn (ProgrammeAssignment $assignment): string => $this->scope->scopeTitle($assignment),
            fn (ProgrammeAssignment $assignment): string => $assignment->user->name,
        ])->values();

        $columns = [
            ['key' => 'scope', 'label' => __('entry.column_scope')],
            ['key' => 'name', 'label' => __('entry.column_name')],
            ['key' => 'email', 'label' => __('entry.column_email')],
            ['key' => 'role', 'label' => __('entry.column_role')],
            ['key' => 'facility', 'label' => __('entry.column_facility')],
        ];

        $cells = function (ProgrammeAssignment $assignment): array {
            return [
                'scope' => $this->scope->scopeTitle($assignment),
                'name' => $assignment->user->name,
                'email' => $assignment->user->email,
                'role' => $assignment->role->label(),
                'facility' => $this->scope->scopePlace($assignment),
            ];
        };

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'programme-access.csv',
                array_column($columns, 'label'),
                $assignments->map(fn (ProgrammeAssignment $assignment): array => array_values($cells($assignment))),
            );
        }

        $rows = $assignments->map(function (ProgrammeAssignment $assignment) use ($cells): array {
            return [
                'id' => (string) $assignment->id,
                'href' => null,
                'cells' => $cells($assignment),
                'controls' => [],
            ];
        })->all();

        return ResourceResponse::page(
            title: __('entry.nav_programme_access'),
            columns: $columns,
            rows: $rows,
            filters: [
                [
                    'key' => 'q',
                    'label' => __('entry.search'),
                    'type' => 'search',
                    'value' => $search,
                ],
            ],
            sort: 'name',
            direction: 'asc',
            from: $assignments->isEmpty() ? null : 1,
            to: $assignments->isEmpty() ? null : $assignments->count(),
            total: $assignments->count(),
            previousUrl: null,
            nextUrl: null,
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
            shell: [
                'description' => __('entry.people_description'),
                'title' => __('entry.nav_people'),
                'tabs' => AdminTabs::people('programme_access'),
            ],
        );
    }
}

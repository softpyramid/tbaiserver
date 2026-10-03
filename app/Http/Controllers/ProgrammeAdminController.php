<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestStatus;
use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\AccessRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\ResourceResponse;
use App\Support\TeamDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammeAdminController extends Controller
{
    public function assignments(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort($request, ['user', 'role'], 'user');

        $query = ProgrammeAssignment::query()->with('user');
        $role = ProgrammeRole::tryFrom($request->string('role')->toString());

        if ($role instanceof ProgrammeRole) {
            $query->where('role', $role);
        }

        $scopeType = ScopeType::tryFrom($request->string('scope_type')->toString());

        if ($scopeType instanceof ScopeType) {
            $query->where('scope_type', $scopeType);
        }

        if ($sort === 'role') {
            $query->orderBy('role', $direction);
        } else {
            $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'programme_assignments.user_id'),
                $direction,
            );
        }

        $columns = [
            ['key' => 'user', 'label' => __('entry.column_user')],
            ['key' => 'email', 'label' => __('entry.column_email')],
            ['key' => 'role', 'label' => __('entry.column_role')],
            ['key' => 'scope_type', 'label' => __('entry.column_scope_type')],
            ['key' => 'scope', 'label' => __('entry.column_scope')],
        ];

        $cells = fn (ProgrammeAssignment $assignment): array => [
            'user' => $assignment->user->name,
            'email' => $assignment->user->email,
            'role' => $assignment->role->label(),
            'scope_type' => $assignment->scope_type === null ? __('entry.scope_all') : $assignment->scope_type->value,
            'scope' => $this->scopeName($assignment),
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'programme-assignments.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (ProgrammeAssignment $assignment): array => array_values($cells($assignment))),
            );
        }

        $page = $query->paginate(15)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_programme_assignments'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (ProgrammeAssignment $assignment): array => [
                'id' => (string) $assignment->id,
                'href' => null,
                'cells' => $cells($assignment),
                'controls' => [],
            ])->values()->all(),
            filters: [
                [
                    'key' => 'role',
                    'label' => __('entry.column_role'),
                    'type' => 'select',
                    'value' => $role instanceof ProgrammeRole ? $role->value : '',
                    'options' => array_merge(
                        [['value' => '', 'label' => __('entry.any')]],
                        ProgrammeRole::options(),
                    ),
                ],
                [
                    'key' => 'scope_type',
                    'label' => __('entry.column_scope_type'),
                    'type' => 'select',
                    'value' => $scopeType instanceof ScopeType ? $scopeType->value : '',
                    'options' => [
                        ['value' => '', 'label' => __('entry.any')],
                        ['value' => ScopeType::Country->value, 'label' => ScopeType::Country->value],
                        ['value' => ScopeType::Province->value, 'label' => ScopeType::Province->value],
                        ['value' => ScopeType::City->value, 'label' => ScopeType::City->value],
                    ],
                ],
            ],
            sort: $sort,
            direction: $direction,
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
        );
    }

    public function accessRequests(Request $request): Response|StreamedResponse
    {
        Gate::authorize('viewAny', AccessRequest::class);

        [$sort, $direction] = ResourceResponse::sort($request, ['created', 'status'], 'created', 'desc');

        $query = AccessRequest::query()->with('reviewer');
        $status = AccessRequestStatus::tryFrom($request->string('status')->toString());

        if ($status instanceof AccessRequestStatus) {
            $query->where('status', $status);
        }

        $search = trim($request->string('q')->toString());

        if ($search !== '') {
            $like = ResourceResponse::like($search);
            $query->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('organisation', 'like', $like);
            });
        }

        if ($sort === 'status') {
            $query->orderBy('status', $direction);
        } else {
            $query->orderBy('created_at', $direction);
        }

        $columns = [
            ['key' => 'name', 'label' => __('entry.column_requester')],
            ['key' => 'email', 'label' => __('entry.column_email')],
            ['key' => 'role', 'label' => __('entry.column_role')],
            ['key' => 'organisation', 'label' => __('entry.column_organisation')],
            ['key' => 'district', 'label' => __('entry.column_district')],
            ['key' => 'status', 'label' => __('entry.column_status')],
            ['key' => 'reviewed_by', 'label' => __('entry.column_reviewed_by')],
        ];

        $cells = fn (AccessRequest $accessRequest): array => [
            'name' => $accessRequest->name,
            'email' => $accessRequest->email,
            'role' => $accessRequest->role,
            'organisation' => $accessRequest->organisation,
            'district' => $accessRequest->district,
            'status' => $accessRequest->status->label(),
            'reviewed_by' => $accessRequest->reviewed_by === null
                ? __('entry.not_available')
                : $accessRequest->reviewer->name,
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'access-requests.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (AccessRequest $accessRequest): array => array_values($cells($accessRequest))),
            );
        }

        $page = $query->paginate(15)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_access_requests'),
            columns: $columns,
            rows: $page->getCollection()->map(function (AccessRequest $accessRequest) use ($cells): array {
                $controls = [];

                if ($accessRequest->status === AccessRequestStatus::Pending) {
                    $controls[] = [
                        'kind' => 'button',
                        'url' => route('admin.access-requests.update', $accessRequest),
                        'method' => 'patch',
                        'label' => __('entry.approve'),
                        'person' => $accessRequest->name,
                        'confirm' => true,
                        'fields' => [
                            ['name' => 'status', 'value' => AccessRequestStatus::Approved->value],
                        ],
                    ];
                    $controls[] = [
                        'kind' => 'button',
                        'url' => route('admin.access-requests.update', $accessRequest),
                        'method' => 'patch',
                        'label' => __('entry.reject'),
                        'person' => $accessRequest->name,
                        'confirm' => true,
                        'fields' => [
                            ['name' => 'status', 'value' => AccessRequestStatus::Rejected->value],
                        ],
                    ];
                }

                return [
                    'id' => (string) $accessRequest->id,
                    'href' => null,
                    'cells' => $cells($accessRequest),
                    'controls' => $controls,
                ];
            })->values()->all(),
            filters: [
                [
                    'key' => 'q',
                    'label' => __('entry.search'),
                    'type' => 'search',
                    'value' => $search,
                ],
                [
                    'key' => 'status',
                    'label' => __('entry.column_status'),
                    'type' => 'select',
                    'value' => $status instanceof AccessRequestStatus ? $status->value : '',
                    'options' => [
                        ['value' => '', 'label' => __('entry.any')],
                        ['value' => AccessRequestStatus::Pending->value, 'label' => AccessRequestStatus::Pending->label()],
                        ['value' => AccessRequestStatus::Approved->value, 'label' => AccessRequestStatus::Approved->label()],
                        ['value' => AccessRequestStatus::Rejected->value, 'label' => AccessRequestStatus::Rejected->label()],
                    ],
                ],
            ],
            sort: $sort,
            direction: $direction,
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
        );
    }

    public function updateAccessRequest(Request $request, AccessRequest $accessRequest, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('review', $accessRequest);

        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AccessRequestStatus::Approved->value,
                AccessRequestStatus::Rejected->value,
            ])],
        ]);

        $status = AccessRequestStatus::from($validated['status']);

        $accessRequest->forceFill([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        $audit->write(
            $actor,
            $status === AccessRequestStatus::Approved ? 'access_request.approved' : 'access_request.rejected',
            $accessRequest,
            ['status' => $status->value],
        );

        return back()->with('status', __('entry.request_reviewed'));
    }

    public function teams(Request $request, TeamDirectory $directory): Response
    {
        Gate::authorize('administer');

        return Inertia::render('admin/teams', $directory->page($request));
    }

    private function scopeName(ProgrammeAssignment $assignment): string
    {
        return match ($assignment->scope_type) {
            null => __('entry.scope_all'),
            ScopeType::Country => $this->placeName(Country::query()->find($assignment->scope_id)),
            ScopeType::Province => $this->placeName(Province::query()->find($assignment->scope_id)),
            ScopeType::City => $this->placeName(City::query()->find($assignment->scope_id)),
        };
    }

    private function placeName(Country|Province|City|null $place): string
    {
        if ($place === null) {
            return __('entry.not_available');
        }

        return $place->name;
    }
}

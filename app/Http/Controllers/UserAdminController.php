<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\ResourceResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAdminController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort($request, ['name', 'email'], 'name');

        $query = User::query()
            ->with(['teamMemberships.team.facility', 'programmeAssignments']);

        $search = trim($request->string('q')->toString());

        if ($search !== '') {
            $like = ResourceResponse::like($search);
            $query->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        $active = $request->string('active')->toString();

        if ($active === '1') {
            $query->whereNull('deactivated_at');
        } elseif ($active === '0') {
            $query->whereNotNull('deactivated_at');
        }

        $query->orderBy($sort, $direction);

        $columns = [
            ['key' => 'name', 'label' => __('entry.facility_name')],
            ['key' => 'email', 'label' => __('entry.column_email')],
            ['key' => 'role', 'label' => __('entry.column_role')],
            ['key' => 'team', 'label' => __('entry.column_team')],
            ['key' => 'assignments', 'label' => __('entry.column_assignments')],
            ['key' => 'active', 'label' => __('entry.active')],
        ];

        $cells = fn (User $user): array => [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $this->roleSummary($user),
            'team' => $this->teamSummary($user),
            'assignments' => $this->assignmentSummary($user),
            'active' => $user->deactivated_at === null ? __('entry.yes') : __('entry.no'),
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'users.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (User $user): array => array_values($cells($user))),
            );
        }

        $page = $query->paginate(15)->withQueryString();
        $roleOptions = array_map(
            fn (TeamRole $role): array => ['value' => $role->value, 'label' => $role->label()],
            TeamRole::cases(),
        );

        $rows = $page->getCollection()->map(function (User $user) use ($cells, $roleOptions): array {
            $controls = [];

            foreach ($user->teamMemberships as $membership) {
                $team = $membership->team;

                if ($team->is_personal || $team->facility === null) {
                    continue;
                }

                $controls[] = [
                    'kind' => 'select',
                    'url' => route('admin.memberships.update', $membership),
                    'method' => 'patch',
                    'name' => 'role',
                    'value' => $membership->role->value,
                    'label' => $team->name,
                    'options' => $roleOptions,
                ];
            }

            $isActive = $user->deactivated_at === null;

            $controls[] = [
                'kind' => 'button',
                'url' => route('admin.users.update', $user),
                'method' => 'patch',
                'label' => $isActive ? __('entry.deactivate') : __('entry.reactivate'),
                'fields' => [
                    ['name' => 'active', 'value' => $isActive ? '0' : '1'],
                ],
            ];

            return [
                'id' => (string) $user->id,
                'href' => null,
                'cells' => $cells($user),
                'controls' => $controls,
            ];
        })->values()->all();

        return ResourceResponse::page(
            title: __('entry.nav_users'),
            columns: $columns,
            rows: $rows,
            filters: [
                [
                    'key' => 'q',
                    'label' => __('entry.search'),
                    'type' => 'search',
                    'value' => $search,
                ],
                [
                    'key' => 'active',
                    'label' => __('entry.active'),
                    'type' => 'select',
                    'value' => $active === '1' || $active === '0' ? $active : '',
                    'options' => [
                        ['value' => '', 'label' => __('entry.any')],
                        ['value' => '1', 'label' => __('entry.active')],
                        ['value' => '0', 'label' => __('entry.inactive')],
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

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('administer');

        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $active = $request->boolean('active');

        if (! $request->has('active')) {
            throw ValidationException::withMessages([
                'active' => __('entry.not_available'),
            ]);
        }

        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages([
                'active' => __('entry.cannot_deactivate_self'),
            ]);
        }

        $user->forceFill([
            'deactivated_at' => $active ? null : now(),
        ])->save();

        $audit->write($actor, $active ? 'user.reactivated' : 'user.deactivated', $user, [
            'active' => $active,
        ]);

        return back()->with(
            'status',
            $active ? __('entry.account_reactivated') : __('entry.account_deactivated'),
        );
    }

    public function updateMembership(Request $request, Membership $membership, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('administer');

        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $membership->load('team', 'user');
        abort_if($membership->team->is_personal || $membership->team->facility === null, 404);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(TeamRole::class)],
        ]);

        $next = TeamRole::from($validated['role']);
        $previous = $membership->role;

        if ($previous !== $next) {
            $membership->role = $next;
            $membership->save();

            $audit->write($actor, 'user.role_changed', $membership->user, [
                'team_id' => $membership->team_id,
                'from' => $previous->value,
                'to' => $next->value,
            ]);
        }

        return back()->with('status', __('entry.role_saved'));
    }

    private function roleSummary(User $user): string
    {
        $roles = $user->teamMemberships
            ->filter(fn (Membership $membership): bool => ! $membership->team->is_personal
                && $membership->team->facility !== null)
            ->map(fn (Membership $membership): string => $membership->role->label())
            ->unique()
            ->values();

        if ($roles->isEmpty()) {
            return __('entry.not_available');
        }

        return $roles->implode(', ');
    }

    private function teamSummary(User $user): string
    {
        $names = $user->teamMemberships
            ->filter(fn (Membership $membership): bool => ! $membership->team->is_personal
                && $membership->team->facility !== null)
            ->map(fn (Membership $membership): string => $membership->team->name)
            ->values();

        if ($names->isEmpty()) {
            return __('entry.not_available');
        }

        return $names->implode(', ');
    }

    private function assignmentSummary(User $user): string
    {
        $labels = $user->programmeAssignments
            ->map(fn ($assignment): string => $assignment->role->label())
            ->unique()
            ->values();

        if ($labels->isEmpty()) {
            return __('entry.not_available');
        }

        return $labels->implode(', ');
    }
}

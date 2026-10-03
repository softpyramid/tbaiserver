<?php

namespace App\Support;

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamArea
{
    /**
     * Shared title, description, and the tabs this person may open.
     *
     * @return array<string, mixed>
     */
    public function header(User $user, Team $team, string $current): array
    {
        return [
            'title' => $current === 'profile'
                ? __('entry.nav_facility_long')
                : __('entry.nav_team_members'),
            'description' => __('entry.team_area_description', ['name' => $team->name]),
            'tabsLabel' => __('entry.team_tabs_label'),
            'teamName' => $team->name,
            'teamSlug' => $team->slug,
            'tabs' => $this->tabs($user, $team, $current),
            'canInvite' => Gate::forUser($user)->allows('inviteMember', $team),
            'inviteRoles' => TeamRole::rosterOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function members(Request $request, User $actor, Team $team): array
    {
        $facility = $team->facility;
        abort_unless($facility instanceof Facility, 404);

        $search = trim($request->string('q')->toString());
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();

        $query = Membership::query()
            ->where('team_id', $team->id)
            ->with(['user.programmeAssignments']);

        if ($search !== '') {
            $like = ResourceResponse::like($search);
            $query->whereHas('user', function ($users) use ($like): void {
                $users->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        $teamRole = TeamRole::tryFrom($role);
        $programmeRole = ProgrammeRole::tryFrom($role);

        if ($teamRole instanceof TeamRole) {
            $query->where('role', $teamRole);
        } elseif ($programmeRole instanceof ProgrammeRole) {
            $query->whereHas('user.programmeAssignments', function ($assignments) use ($programmeRole): void {
                $assignments->where('role', $programmeRole);
            });
        }

        if ($status === 'active') {
            $query->whereHas('user', fn ($users) => $users->whereNull('deactivated_at'));
        } elseif ($status === 'inactive') {
            $query->whereHas('user', fn ($users) => $users->whereNotNull('deactivated_at'));
        }

        $query->orderBy(
            User::query()->select('name')->whereColumn('users.id', 'team_members.user_id'),
        );

        $page = $query->paginate(15)->withQueryString();
        $returns = $this->returnsThisMonth($facility);
        $ownerCount = $team->memberships()->where('role', TeamRole::Owner->value)->count();

        return [
            'area' => $this->header($actor, $team, 'members'),
            'cards' => $this->cards($team, $facility),
            'filters' => [
                'q' => $search,
                'role' => $role,
                'status' => $status,
            ],
            'roleOptions' => $this->roleFilterOptions(),
            'statusOptions' => [
                ['value' => '', 'label' => __('entry.any')],
                ['value' => 'active', 'label' => __('entry.active')],
                ['value' => 'inactive', 'label' => __('entry.inactive')],
            ],
            'members' => collect($page->items())->map(function (Membership $membership) use ($actor, $team, $returns, $ownerCount): array {
                $member = $membership->user;
                $badge = $this->badge($member, $membership->role);
                $seen = LastActive::describe($member->last_seen_at);
                $lastOwner = $membership->role === TeamRole::Owner && $ownerCount <= 1;
                $ownRole = $actor->is($member) && $actor->teamRole($team) === TeamRole::Owner;

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $badge['value'],
                    'roleLabel' => $badge['label'],
                    'joined' => $membership->created_at?->timezone((string) config('app.timezone'))->format('j M Y') ?? __('entry.not_available'),
                    'lastActive' => $seen['label'],
                    'activeNow' => $seen['activeNow'],
                    'returnsThisMonth' => (int) ($returns[$member->id] ?? 0),
                    'inactive' => $member->deactivated_at !== null,
                    'actions' => $this->memberActions($actor, $team, $member, $ownRole, $lastOwner),
                ];
            })->values()->all(),
            'pagination' => $this->pagination($page),
            'labels' => [
                'invite' => __('entry.invite_member'),
                'search' => __('entry.search_members'),
                'role' => __('entry.column_role'),
                'status' => __('entry.column_status'),
                'apply' => __('entry.apply'),
                'clear' => __('entry.clear'),
                'actions' => __('entry.actions'),
                'member' => __('entry.column_member'),
                'returns' => __('entry.returns_this_month'),
                'joined' => __('entry.joined'),
                'lastActive' => __('entry.last_active'),
                'empty' => __('entry.members_no_match'),
                'current' => __('entry.current_members', ['count' => $page->total()]),
                'previous' => __('entry.previous_page'),
                'next' => __('entry.next_page'),
                'inactive' => __('entry.inactive'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invites(Request $request, User $actor, Team $team): array
    {
        $page = $team->invitations()
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'area' => $this->header($actor, $team, 'invites'),
            'invitations' => collect($page->items())->map(function (TeamInvitation $invitation) use ($team, $actor): array {
                $canManage = Gate::forUser($actor)->allows('inviteMember', $team);

                return [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'roleLabel' => $invitation->role->rosterLabel(),
                    'sent' => $invitation->created_at?->timezone((string) config('app.timezone'))->format('j M Y') ?? __('entry.not_available'),
                    'actions' => $canManage ? [
                        [
                            'kind' => 'post',
                            'label' => __('entry.resend_invite'),
                            'url' => route('facilities.invitations.resend', ['current_team' => $team->slug, 'invitation' => $invitation]),
                            'confirm' => null,
                            'fields' => [],
                        ],
                        [
                            'kind' => 'delete',
                            'label' => __('entry.cancel_invite'),
                            'url' => route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation]),
                        ],
                    ] : [],
                ];
            })->values()->all(),
            'pagination' => $this->pagination($page),
            'labels' => [
                'invite' => __('entry.invite_member'),
                'empty' => __('entry.invites_empty'),
                'email' => __('entry.column_email'),
                'role' => __('entry.column_role'),
                'sent' => __('entry.column_when'),
                'actions' => __('entry.actions'),
                'previous' => __('entry.previous_page'),
                'next' => __('entry.next_page'),
                'heading' => __('entry.tab_invites'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function roles(User $actor, Team $team): array
    {
        return [
            'area' => $this->header($actor, $team, 'roles'),
            'intro' => __('entry.roles_intro'),
            'roles' => [
                ['role' => TeamRole::Owner->rosterLabel(), 'access' => __('entry.role_access_in_charge')],
                ['role' => TeamRole::Admin->rosterLabel(), 'access' => __('entry.role_access_senior')],
                ['role' => TeamRole::Member->rosterLabel(), 'access' => __('entry.role_access_technician')],
                ['role' => ProgrammeRole::CityOfficer->label(), 'access' => __('entry.role_access_city')],
                ['role' => ProgrammeRole::ProvincialViewer->label(), 'access' => __('entry.role_access_province')],
                ['role' => ProgrammeRole::CountryViewer->label(), 'access' => __('entry.role_access_country')],
                ['role' => ProgrammeRole::TbaiAdmin->label(), 'access' => __('entry.role_access_admin')],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activity(Request $request, User $actor, Team $team): array
    {
        $facility = $team->facility;
        abort_unless($facility instanceof Facility, 404);

        $page = AuditLog::query()
            ->with('actor')
            ->where(function ($query) use ($team, $facility): void {
                $query->where('properties->team_id', $team->id)
                    ->orWhere(function ($match) use ($facility): void {
                        $match->where('subject_type', Facility::class)
                            ->where('subject_id', $facility->id);
                    });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'area' => $this->header($actor, $team, 'activity'),
            'events' => collect($page->items())->map(function (AuditLog $log): array {
                return [
                    'id' => $log->id,
                    'when' => $log->created_at->timezone((string) config('app.timezone'))->format('j M Y H:i'),
                    'who' => $log->actor?->name ?? __('entry.not_available'),
                    'action' => $this->activityLabel($log->action),
                ];
            })->values()->all(),
            'pagination' => $this->pagination($page),
            'labels' => [
                'empty' => __('entry.activity_empty'),
                'when' => __('entry.column_when'),
                'who' => __('entry.column_who'),
                'action' => __('entry.column_action'),
                'previous' => __('entry.previous_page'),
                'next' => __('entry.next_page'),
                'heading' => __('entry.tab_activity'),
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, href: string, current: bool}>
     */
    private function tabs(User $user, Team $team, string $current): array
    {
        $facility = $team->facility;
        $open = Gate::forUser($user)->allows('viewRoster', $team);
        $profile = $facility instanceof Facility && Gate::forUser($user)->allows('openProfile', $facility);

        $tabs = [
            ['id' => 'members', 'label' => __('entry.tab_members'), 'href' => route('facilities.members', $team), 'visible' => $open],
            ['id' => 'invites', 'label' => __('entry.tab_invites'), 'href' => route('facilities.invites', $team), 'visible' => $open],
            ['id' => 'roles', 'label' => __('entry.tab_roles'), 'href' => route('facilities.roles', $team), 'visible' => $open],
            ['id' => 'activity', 'label' => __('entry.tab_activity'), 'href' => route('facilities.activity', $team), 'visible' => $open],
            ['id' => 'profile', 'label' => __('entry.tab_profile'), 'href' => route('facilities.edit', $team), 'visible' => $profile],
        ];

        return array_values(array_map(
            fn (array $tab): array => [
                'id' => $tab['id'],
                'label' => $tab['label'],
                'href' => $tab['href'],
                'current' => $tab['id'] === $current,
            ],
            array_values(array_filter($tabs, fn (array $tab): bool => $tab['visible'])),
        ));
    }

    /**
     * @return list<array{key: string, label: string, figure: string, caption: string}>
     */
    private function cards(Team $team, Facility $facility): array
    {
        $memberIds = $team->memberships()->pluck('user_id');
        $since = now()->subDays(7);
        $seen = User::query()
            ->whereIn('id', $memberIds)
            ->where('last_seen_at', '>=', $since)
            ->pluck('id');
        $returned = DailyReturn::query()
            ->where('facility_id', $facility->id)
            ->where('reported_on', '>=', $since->toDateString())
            ->whereIn('submitted_by', $memberIds)
            ->pluck('submitted_by');
        $activeWeek = $seen->merge($returned)->unique()->count();
        $inCharge = $team->memberships()->where('role', TeamRole::Owner->value)->count();
        $pending = $team->invitations()->whereNull('accepted_at')->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();
        $total = $memberIds->count();

        return [
            [
                'key' => 'members',
                'label' => __('entry.team_card_members'),
                'figure' => trans_choice('entry.count_members', $total, ['count' => $total]),
                'caption' => __('entry.team_card_members_caption'),
            ],
            [
                'key' => 'in_charge',
                'label' => __('entry.team_card_in_charge'),
                'figure' => trans_choice('entry.count_in_charge', $inCharge, ['count' => $inCharge]),
                'caption' => __('entry.team_card_in_charge_caption'),
            ],
            [
                'key' => 'active_week',
                'label' => __('entry.team_card_active_week'),
                'figure' => trans_choice('entry.count_members', $activeWeek, ['count' => $activeWeek]),
                'caption' => __('entry.team_card_active_week_caption'),
            ],
            [
                'key' => 'pending',
                'label' => __('entry.team_card_pending'),
                'figure' => trans_choice('entry.count_invites', $pending, ['count' => $pending]),
                'caption' => __('entry.team_card_pending_caption'),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function returnsThisMonth(Facility $facility): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return DailyReturn::query()
            ->where('facility_id', $facility->id)
            ->whereBetween('reported_on', [$start, $end])
            ->selectRaw('submitted_by, count(*) as total')
            ->groupBy('submitted_by')
            ->pluck('total', 'submitted_by')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array{value: string, label: string}
     */
    private function badge(User $user, TeamRole $role): array
    {
        $assignment = $user->programmeAssignments->first();

        if ($assignment !== null) {
            return [
                'value' => $assignment->role->value,
                'label' => $assignment->role->label(),
            ];
        }

        return [
            'value' => $role->value,
            'label' => $role->rosterLabel(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memberActions(User $actor, Team $team, User $member, bool $ownRole, bool $lastOwner): array
    {
        $actions = [];

        if (Gate::forUser($actor)->allows('updateMember', $team) && ! $ownRole && ! $lastOwner) {
            $actions[] = [
                'kind' => 'roles',
                'label' => __('entry.change_role'),
                'url' => route('teams.members.update', ['team' => $team->slug, 'user' => $member->id]),
                'options' => TeamRole::rosterOptions(),
            ];
        }

        $invitation = $team->invitations()
            ->where('email', $member->email)
            ->whereNull('accepted_at')
            ->first();

        if ($invitation instanceof TeamInvitation && Gate::forUser($actor)->allows('inviteMember', $team)) {
            $actions[] = [
                'kind' => 'post',
                'label' => __('entry.resend_invite'),
                'url' => route('facilities.invitations.resend', ['current_team' => $team->slug, 'invitation' => $invitation]),
                'confirm' => null,
                'fields' => [],
            ];
        }

        if ($this->canToggleActive($actor, $team, $member, $lastOwner)) {
            $inactive = $member->deactivated_at !== null;
            $actions[] = [
                'kind' => 'post',
                'label' => $inactive ? __('entry.reactivate') : __('entry.deactivate'),
                'url' => route('facilities.members.active', ['current_team' => $team->slug, 'user' => $member->id]),
                'confirm' => $inactive ? __('entry.reactivate_confirm') : __('entry.deactivate_confirm'),
                'fields' => ['active' => $inactive ? '1' : '0'],
            ];
        }

        return $actions;
    }

    private function canToggleActive(User $actor, Team $team, User $member, bool $lastOwner): bool
    {
        if (! Gate::forUser($actor)->allows('deactivateMember', $team)) {
            return false;
        }

        if ($actor->is($member) || $lastOwner) {
            return false;
        }

        return true;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => __('entry.any')],
            ['value' => TeamRole::Member->value, 'label' => TeamRole::Member->rosterLabel()],
            ['value' => TeamRole::Owner->value, 'label' => TeamRole::Owner->rosterLabel()],
            ['value' => TeamRole::Admin->value, 'label' => TeamRole::Admin->rosterLabel()],
            ...ProgrammeRole::options(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array{showing: string, previousUrl: string|null, nextUrl: string|null}
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'showing' => $page->total() === 0 ? '' : __('entry.showing_rows', [
                'from' => (string) $page->firstItem(),
                'to' => (string) $page->lastItem(),
                'total' => (string) $page->total(),
            ]),
            'previousUrl' => $page->previousPageUrl(),
            'nextUrl' => $page->nextPageUrl(),
        ];
    }

    private function activityLabel(string $action): string
    {
        return match ($action) {
            'team.invitation.sent' => __('entry.activity_invite_sent'),
            'team.invitation.resent' => __('entry.activity_invite_resent'),
            'team.invitation.cancelled' => __('entry.activity_invite_cancelled'),
            'team.member.role_updated' => __('entry.activity_role_updated'),
            'team.member.deactivated' => __('entry.activity_deactivated'),
            'team.member.reactivated' => __('entry.activity_reactivated'),
            'facility.updated' => __('entry.activity_facility_updated'),
            'facility.created' => __('entry.activity_facility_created'),
            default => __('entry.activity_recorded'),
        };
    }
}

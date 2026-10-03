<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FacilityMemberController extends Controller
{
    /**
     * Roster for the facility in-charge.
     */
    public function __invoke(Team $current_team): Response
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        Gate::authorize('viewRoster', $current_team);

        $members = $current_team->memberships()
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $membership): string => mb_strtolower($membership->user->name))
            ->values()
            ->map(fn (Membership $membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $this->roleLabel($membership->role),
            ])
            ->all();

        return Inertia::render('facilities/members', [
            'title' => __('entry.nav_team_members'),
            'name' => $current_team->name,
            'empty' => __('entry.members_empty'),
            'columns' => [
                ['key' => 'name', 'label' => __('entry.facility_name')],
                ['key' => 'email', 'label' => __('entry.column_email')],
                ['key' => 'role', 'label' => __('entry.column_role')],
            ],
            'members' => $members,
            'manageUrl' => Gate::allows('update', $current_team)
                ? route('teams.edit', $current_team)
                : null,
            'manageLabel' => __('entry.edit'),
        ]);
    }

    private function roleLabel(TeamRole $role): string
    {
        return $role->label();
    }
}

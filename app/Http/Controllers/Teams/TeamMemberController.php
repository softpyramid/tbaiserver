<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TeamRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_if($actor->is($user) && $actor->teamRole($team) === TeamRole::Owner, 403);
        abort_if($team->isLastOwner($user), 403);

        $membership = $team->memberships()->where('user_id', $user->id)->firstOrFail();
        $from = $membership->role;
        $choice = $request->choice();

        $membership->update(['role' => $choice->role, 'designation' => $choice->designation]);

        $audit->write($actor, 'team.member.role_updated', $user, [
            'team_id' => $team->id,
            'from' => $from->value,
            'to' => $choice->role->value,
            'designation' => $choice->designation?->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.role_saved')]);

        return TeamRedirect::to($team, 'members');
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The facility in-charge cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return TeamRedirect::to($team, 'members');
    }
}

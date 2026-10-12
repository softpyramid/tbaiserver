<?php

namespace App\Http\Controllers\Teams;

use App\Enums\FrontlineDesignation;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Support\AuditLogger;
use App\Support\TeamRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTeamInvitationRequest $request, Team $team, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $role = TeamRole::from($request->validated('role'));
        $designation = $request->validated('designation');

        $invitation = $team->invitations()->create([
            'email' => $request->validated('email'),
            'role' => $role,
            'designation' => $role->takesDesignation() && is_string($designation)
                ? FrontlineDesignation::from($designation)
                : null,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        $audit->write($actor, 'team.invitation.sent', $invitation, [
            'team_id' => $team->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'designation' => $invitation->designation?->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return TeamRedirect::to($team, 'invites');
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Team $team, TeamInvitation $invitation, AuditLogger $audit): RedirectResponse
    {
        abort_unless($invitation->team_id === $team->id, 404);

        Gate::authorize('cancelInvitation', $team);

        $actor = request()->user();

        $invitation->delete();

        if ($actor instanceof User) {
            $audit->write($actor, 'team.invitation.cancelled', $invitation, [
                'team_id' => $team->id,
                'email' => $invitation->email,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return TeamRedirect::to($team, 'invites');
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation) {
            $team = $invitation->team;

            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role, 'designation' => $invitation->designation],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }
}

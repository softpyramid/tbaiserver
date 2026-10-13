<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Support\AuditLogger;
use App\Support\TeamArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class TeamAreaController extends Controller
{
    public function members(Request $request, Team $current_team, TeamArea $area): Response
    {
        Gate::authorize('viewRoster', $current_team);
        $actor = $this->actor($request);

        return Inertia::render('facilities/members', $area->members($request, $actor, $current_team));
    }

    public function invites(Request $request, Team $current_team, TeamArea $area): Response
    {
        Gate::authorize('viewRoster', $current_team);
        $actor = $this->actor($request);

        return Inertia::render('facilities/invites', $area->invites($request, $actor, $current_team));
    }

    public function activity(Request $request, Team $current_team, TeamArea $area): Response
    {
        Gate::authorize('viewRoster', $current_team);

        return Inertia::render('facilities/activity', $area->activity($request, $this->actor($request), $current_team));
    }

    public function active(Request $request, Team $current_team, User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('deactivateMember', $current_team);

        $actor = $this->actor($request);
        $active = $request->boolean('active');

        if (! $request->has('active')) {
            abort(403);
        }

        abort_if($actor->is($user), 403);
        abort_if(! $active && $current_team->isLastOwner($user), 403);

        $membership = $current_team->memberships()->where('user_id', $user->id)->first();
        abort_if($membership === null, 404);

        $user->forceFill([
            'deactivated_at' => $active ? null : now(),
        ])->save();

        $audit->write(
            $actor,
            $active ? 'team.member.reactivated' : 'team.member.deactivated',
            $user,
            [
                'team_id' => $current_team->id,
                'active' => $active,
            ],
        );

        return back();
    }

    public function resend(Request $request, Team $current_team, TeamInvitation $invitation, AuditLogger $audit): RedirectResponse
    {
        abort_unless($invitation->team_id === $current_team->id, 404);
        abort_if($invitation->isAccepted(), 404);

        Gate::authorize('manageInvitation', [$current_team, $invitation]);

        $actor = $this->actor($request);

        $invitation->forceFill([
            'expires_at' => now()->addDays(3),
        ])->save();

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        $audit->write($actor, 'team.invitation.resent', $invitation, [
            'team_id' => $current_team->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

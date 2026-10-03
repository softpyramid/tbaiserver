<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $user->belongsToTeam($team)
            && ! $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the team.
     */
    public function updateMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CancelInvitation);
    }

    /**
     * Facility in-charge, senior technician, and TB-AI admin may open the team area.
     * Technicians and programme viewers may not.
     */
    public function viewRoster(User $user, Team $team): bool
    {
        if ($team->is_personal || $team->facility === null) {
            return false;
        }

        if ($user->isTbaiAdmin()) {
            return true;
        }

        $role = $user->teamRole($team);

        return $role === TeamRole::Owner || $role === TeamRole::Admin;
    }

    /**
     * Deactivate or reactivate a facility account from the team roster.
     */
    public function deactivateMember(User $user, Team $team): bool
    {
        if (! $this->viewRoster($user, $team)) {
            return false;
        }

        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $user->hasTeamPermission($team, TeamPermission::RemoveMember)
            || $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Facility in-charge may open returns history for their facility.
     */
    public function viewReturns(User $user, Team $team): bool
    {
        if ($team->facility === null) {
            return false;
        }

        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $user->teamRole($team) === TeamRole::Owner;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        if ($team->is_personal) {
            return false;
        }

        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $user->teamRole($team) === TeamRole::Owner;
    }
}

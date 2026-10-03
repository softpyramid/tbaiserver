<?php

namespace App\Support;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;

class ReturnDatePolicy
{
    /**
     * Members may open today and yesterday. Owners and team admins may open any past date.
     * TB-AI admins may open any past date. Future dates are closed.
     */
    public function canView(User $user, Team $team, CarbonInterface $date): bool
    {
        if ($this->isFuture($date)) {
            return false;
        }

        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $this->canEdit($user, $team, $date);
    }

    /**
     * Members may save today and yesterday. Owners and team admins may backfill.
     */
    public function canEdit(User $user, Team $team, CarbonInterface $date): bool
    {
        if ($this->isFuture($date)) {
            return false;
        }

        $role = $user->teamRole($team);

        if ($role === TeamRole::Owner || $role === TeamRole::Admin) {
            return true;
        }

        if ($role !== TeamRole::Member) {
            return false;
        }

        $day = $date->copy()->timezone(config('app.timezone'))->startOfDay();
        $yesterday = now()->startOfDay()->subDay();

        return $day->greaterThanOrEqualTo($yesterday);
    }

    public function canSeeHistory(User $user, Team $team): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        $role = $user->teamRole($team);

        return $role === TeamRole::Owner || $role === TeamRole::Admin;
    }

    private function isFuture(CarbonInterface $date): bool
    {
        $day = $date->copy()->timezone(config('app.timezone'))->startOfDay();

        return $day->greaterThan(now()->startOfDay());
    }
}

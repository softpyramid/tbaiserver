<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class TeamRedirect
{
    /**
     * Facility teams return to the team area. Other teams stay on settings.
     */
    public static function to(Team $team, string $tab): RedirectResponse
    {
        if ($team->facility()->exists()) {
            $name = match ($tab) {
                'invites' => 'facilities.invites',
                default => 'facilities.members',
            };

            return to_route($name, ['current_team' => $team->slug]);
        }

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}

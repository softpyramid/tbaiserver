<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TodayShortcutController extends Controller
{
    /**
     * Home-screen entry. Sends facility staff to today's register.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $facilityTeam = $this->facilityTeam($user);

        if ($facilityTeam !== null) {
            return redirect()->route('returns.today', $facilityTeam);
        }

        $fallback = $user->currentTeam ?? $user->personalTeam();

        if ($fallback === null) {
            return redirect()->route('home');
        }

        return redirect()->route('dashboard', $fallback);
    }

    private function facilityTeam(User $user): ?Team
    {
        $current = $user->currentTeam;
        $current?->loadMissing('facility');

        if ($current?->facility !== null && ($user->belongsToTeam($current) || $user->isTbaiAdmin())) {
            return $current;
        }

        $team = $user->teams()->whereHas('facility')->first();

        return $team instanceof Team ? $team : null;
    }
}

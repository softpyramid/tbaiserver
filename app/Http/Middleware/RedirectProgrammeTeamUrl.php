<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use App\Support\RoleHome;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectProgrammeTeamUrl
{
    /**
     * Programme groups and personal teams are not facility URLs.
     * Send those old links to the role home.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->route('current_team');

        if (is_string($team)) {
            $team = Team::query()->where('slug', $team)->first();
        }

        if ($team instanceof Team) {
            $team->loadMissing('facility');

            if ($team->facility === null) {
                $user = $request->user();
                $target = $user instanceof User
                    ? app(RoleHome::class)->url($user)
                    : route('login');

                // 302, not 301: the target depends on who is signed in, and browsers
                // cache a 301, so a shared device could replay another role's home.
                return redirect()->to($target);
            }
        }

        return $next($request);
    }
}

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

        if ($team instanceof Team) {
            $team->loadMissing('facility');

            if ($team->facility === null) {
                $user = $request->user();
                $target = $user instanceof User
                    ? app(RoleHome::class)->url($user)
                    : route('login');

                return redirect()->to($target, 301);
            }
        }

        return $next($request);
    }
}

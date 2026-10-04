<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, Impersonation $impersonation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        Gate::authorize('impersonate', $user);

        $impersonation->start($request, $actor, $user);

        return redirect()->to(app(RoleHome::class)->url($user));
    }

    public function stop(Request $request, Impersonation $impersonation): RedirectResponse
    {
        $current = $request->user();
        abort_unless($current instanceof User && $impersonation->active($request), 403);

        $next = $impersonation->stop($request, $current);

        return $next === null ? redirect()->route('login') : redirect()->to($next);
    }
}

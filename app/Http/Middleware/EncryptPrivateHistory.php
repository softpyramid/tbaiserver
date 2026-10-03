<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EncryptPrivateHistory
{
    /**
     * Keep signed-in pages encrypted in the browser history, so Back after
     * logout cannot redraw a private page from the history state.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            Inertia::encryptHistory();
        }

        return $next($request);
    }
}

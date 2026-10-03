<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * Send a signed-out person to the login page and tell the browser to
     * forget every encrypted page it kept in history.
     */
    public function toResponse($request): Response
    {
        $response = $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->route('login');

        if (! $request->wantsJson()) {
            Inertia::clearHistory();
        }

        // The person is signed out by now, so mark this answer private by hand.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}

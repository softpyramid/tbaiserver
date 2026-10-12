<?php

namespace App\Http\Middleware;

use App\Support\Suspect\PersonalFields;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RejectPersonalFields
{
    /**
     * Refuse any request that carries a phone number, national ID, CNIC, MR
     * number or a bare "name", on every suspect screening endpoint. The
     * patient name and register number of a named screening are accepted
     * only in the body of the start request, never inside answers, never on
     * another endpoint and never in a query string (which would reach logs).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $namedAllowed = $request->isMethod('POST') && $request->routeIs('api.suspect.assessments.store');
        $found = array_values(array_unique([
            ...PersonalFields::found($request->query()),
            ...PersonalFields::found($request->all(), $namedAllowed),
        ]));

        if ($found !== []) {
            throw ValidationException::withMessages(array_fill_keys($found, __('entry.suspect_no_identifiers')));
        }

        return $next($request);
    }
}

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
     * Refuse any request that carries a name, phone, national ID, CNIC or
     * record number, on every suspect screening endpoint.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $found = PersonalFields::found([...$request->query(), ...$request->all()]);

        if ($found !== []) {
            throw ValidationException::withMessages(array_fill_keys($found, __('entry.suspect_no_identifiers')));
        }

        return $next($request);
    }
}

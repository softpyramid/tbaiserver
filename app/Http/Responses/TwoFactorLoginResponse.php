<?php

namespace App\Http\Responses;

use App\Support\IntendedUrl;
use App\Support\RoleHome;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user !== null) {
            app(IntendedUrl::class)->discardIfNotPermitted($request, $user);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended($user === null ? url('/') : app(RoleHome::class)->url($user));
    }
}

<?php

namespace App\Http\Responses;

use App\Support\RoleHome;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();
        $home = $user === null ? url('/') : app(RoleHome::class)->url($user);
        $separator = str_contains($home, '?') ? '&' : '?';

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($home.$separator.'verified=1');
    }
}

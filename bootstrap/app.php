<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Http\Middleware\TouchLastSeen;
use App\Models\User;
use App\Support\RoleHome;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureAccountIsActive::class,
            TouchLastSeen::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || ($request->expectsJson() && ! $request->headers->has('X-Inertia')),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();
            $copy = match ($status) {
                403 => [__('entry.error_not_allowed'), __('entry.error_not_allowed_reason')],
                404 => [__('entry.error_not_found'), __('entry.error_not_found_reason')],
                419 => [__('entry.error_session'), __('entry.error_session_reason')],
                default => null,
            };

            if ($copy === null || ($request->expectsJson() && ! $request->headers->has('X-Inertia'))) {
                return $response;
            }

            $user = $request->user();
            $home = $user instanceof User
                ? app(RoleHome::class)->url($user)
                : route('home');

            return Inertia::render('errors/http', [
                'status' => $status,
                'title' => $copy[0],
                'reason' => $copy[1],
                'homeUrl' => $home,
                'homeLabel' => __('entry.back_home'),
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();

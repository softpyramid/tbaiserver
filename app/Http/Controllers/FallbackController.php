<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FallbackController extends Controller
{
    /**
     * Answer a URL that no route serves.
     *
     * This route exists so the not found page runs through the web
     * middleware (session, shared props, security headers). A URL that exists
     * for another method keeps its 405 answer.
     */
    public function __invoke(Request $request, Router $router): never
    {
        $allowed = [];

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                $router->getRoutes()->match(Request::create($request->getRequestUri(), $method));
                $allowed[] = $method;
            } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
                continue;
            }
        }

        if ($allowed !== []) {
            abort(405, '', ['Allow' => implode(', ', $allowed)]);
        }

        abort(404);
    }
}

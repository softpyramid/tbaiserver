<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    /**
     * Home-screen install metadata. start_url opens today's register.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'id' => '/today',
            'name' => (string) config('app.name'),
            'short_name' => (string) config('app.name'),
            'description' => __('entry.manifest_description'),
            'start_url' => '/today',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => '#14294c',
            'icons' => [
                [
                    'src' => '/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}

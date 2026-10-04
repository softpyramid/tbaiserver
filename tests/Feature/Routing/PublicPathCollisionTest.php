<?php

use Illuminate\Support\Facades\Route;

test('web routes do not share a first segment with a public file or directory', function () {
    $segments = webRouteFirstSegments();
    $reservedDirectories = ['storage', 'build', 'icons', 'geo', 'cc-assets'];

    foreach ($reservedDirectories as $directory) {
        if (is_dir(public_path($directory))) {
            expect($segments)->not->toContain($directory);
        }
    }

    $collisions = [];

    foreach ($segments as $segment) {
        $path = public_path($segment);

        if (! file_exists($path)) {
            continue;
        }

        if ($segment === 'robots.txt' && is_file($path)) {
            continue;
        }

        $collisions[] = $segment;
    }

    expect($collisions)->toBe([]);

    $contents = file_get_contents(public_path('robots.txt'));

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');

    expect($this->get('/robots.txt')->getContent())->toBe(is_string($contents) ? $contents : '');
});

/**
 * @return list<string>
 */
function webRouteFirstSegments(): array
{
    $segments = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('web', $route->middleware(), true)) {
            continue;
        }

        $uri = trim($route->uri(), '/');

        if ($uri === '') {
            continue;
        }

        $segment = explode('/', $uri)[0];

        if ($segment === '' || str_contains($segment, '{')) {
            continue;
        }

        $segments[$segment] = $segment;
    }

    return array_values($segments);
}

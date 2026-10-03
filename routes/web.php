<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('robots.txt', function (): Response {
    $contents = file_get_contents(public_path('robots.txt'));

    return response(is_string($contents) ? $contents : '', 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('robots');

Route::get('sitemap.xml', function (): Response {
    $locations = ['/', '/privacy', '/terms', '/request-access'];

    $urls = array_map(
        fn (string $path): string => '  <url><loc>'.e(url($path)).'</loc></url>',
        $locations,
    );

    $xml = implode("\n", [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ...$urls,
        '</urlset>',
        '',
    ]);

    return response($xml, 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap');

Route::inertia('/', 'welcome')->name('home');
Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');

Route::get('request-access', [AccessRequestController::class, 'create'])->name('access-requests.create');
Route::post('access-requests', [AccessRequestController::class, 'store'])
    ->middleware('throttle:access-requests')
    ->name('access-requests.store');
Route::redirect('register', '/request-access');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';

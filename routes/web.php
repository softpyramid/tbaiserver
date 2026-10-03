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

Route::inertia('/', 'welcome')->name('home');
Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');

Route::get('request-access', [AccessRequestController::class, 'create'])->name('access-requests.create');
Route::post('access-requests', [AccessRequestController::class, 'store'])
    ->middleware('throttle:5,1')
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

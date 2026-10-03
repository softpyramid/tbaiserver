<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AuditLogAdminController;
use App\Http\Controllers\CommandCenterController;
use App\Http\Controllers\DailyReturnAdminController;
use App\Http\Controllers\DailyReturnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FacilityHomeController;
use App\Http\Controllers\FacilityReturnHistoryController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\ProgrammeAdminController;
use App\Http\Controllers\ProgrammeAssignmentController;
use App\Http\Controllers\ReturnRevisionAdminController;
use App\Http\Controllers\ScreeningController;
use App\Http\Controllers\ScreeningSessionAdminController;
use App\Http\Controllers\TeamAreaController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TodayShortcutController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\WebManifestController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\RedirectProgrammeTeamUrl;
use App\Models\Team;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('robots.txt', function (): Response {
    $contents = file_get_contents(public_path('robots.txt'));

    return response(is_string($contents) ? $contents : '', 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('robots');

Route::get('sitemap.xml', function (): Response {
    $locations = ['/', '/privacy', '/terms', '/about', '/request-access'];

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

Route::get('/', function () {
    $showValidatedClaims = (bool) config('tbai.show_validated_claims');

    return Inertia::render('welcome', [
        'featureTiles' => $showValidatedClaims
            ? config('tbai.validated_tiles')
            : config('tbai.neutral_tiles'),
        'testimonials' => $showValidatedClaims ? config('tbai.testimonials') : [],
        'supporters' => config('tbai.show_supporter_logos')
            ? config('tbai.supporters')
            : [],
    ]);
})->name('home');
Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');
Route::inertia('/about', 'about')->name('about');

Route::get('request-access', [AccessRequestController::class, 'create'])->name('access-requests.create');
Route::post('access-requests', [AccessRequestController::class, 'store'])
    ->middleware('throttle:access-requests')
    ->name('access-requests.store');
Route::redirect('register', '/request-access');

Route::get('manifest.webmanifest', WebManifestController::class)->name('manifest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('today', TodayShortcutController::class)->name('returns.home');

    Route::post('programme-assignments', [ProgrammeAssignmentController::class, 'store'])->name('programme-assignments.store');

    Route::get('command', [CommandCenterController::class, 'index'])->name('command.index');
    Route::get('command/map', [CommandCenterController::class, 'map'])->name('command.map');
    Route::get('command/alerts', [CommandCenterController::class, 'alerts'])->name('command.alerts');
    Route::patch('command/alerts/rules/{alertRule}', [CommandCenterController::class, 'updateAlertRule'])->name('command.alerts.update');
    Route::get('command/drivers', [CommandCenterController::class, 'drivers'])
        ->middleware('feature:drivers')
        ->name('command.drivers');
    Route::get('command/incidence', [CommandCenterController::class, 'incidence'])
        ->middleware('feature:incidence')
        ->name('command.incidence');
    Route::post('command/incidence', [CommandCenterController::class, 'storeIncidence'])
        ->middleware('feature:incidence')
        ->name('command.incidence.store');
    Route::get('command/sectors/{sector}', [CommandCenterController::class, 'sector'])
        ->whereIn('sector', ['public', 'private', 'ppm'])
        ->name('command.sectors.show');
    Route::get('command/sectors/{sector}/districts/{city}', [CommandCenterController::class, 'district'])
        ->whereIn('sector', ['public', 'private', 'ppm'])
        ->name('command.districts.show');
    Route::get('command/facilities', [FacilityController::class, 'scoped'])->name('command.facilities');
    Route::get('command/returns', [CommandCenterController::class, 'returns'])->name('command.returns');
    Route::get('command/returns/{dailyReturn}', [CommandCenterController::class, 'showReturn'])->name('command.returns.show');

    Route::get('admin', fn () => redirect()->route('command.index'))->name('admin.home');
    Route::get('admin/facilities', [FacilityController::class, 'index'])->name('facilities.index');
    Route::post('admin/facilities/{facility}/deactivate', [FacilityController::class, 'deactivate'])->name('facilities.deactivate');
    Route::get('admin/facilities/create', [FacilityController::class, 'create'])->name('facilities.create');
    Route::post('admin/facilities', [FacilityController::class, 'store'])->name('facilities.store');

    Route::get('admin/daily-returns', [DailyReturnAdminController::class, 'index'])->name('admin.daily-returns.index');
    Route::get('admin/daily-returns/{dailyReturn}', [DailyReturnAdminController::class, 'show'])->name('admin.daily-returns.show');
    Route::get('admin/return-revisions', [ReturnRevisionAdminController::class, 'index'])->name('admin.revisions.index');
    Route::get('admin/screening-sessions', [ScreeningSessionAdminController::class, 'index'])
        ->middleware('feature:screening')
        ->name('admin.screening-sessions.index');
    Route::get('admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::patch('admin/users/{user}', [UserAdminController::class, 'update'])->name('admin.users.update');
    Route::patch('admin/memberships/{membership}', [UserAdminController::class, 'updateMembership'])->name('admin.memberships.update');
    Route::get('admin/programme-assignments', [ProgrammeAdminController::class, 'assignments'])->name('admin.programme-assignments.index');
    Route::get('admin/access-requests', [ProgrammeAdminController::class, 'accessRequests'])->name('admin.access-requests.index');
    Route::patch('admin/access-requests/{accessRequest}', [ProgrammeAdminController::class, 'updateAccessRequest'])->name('admin.access-requests.update');
    Route::get('admin/programme-access', [ProgrammeAdminController::class, 'access'])->name('admin.programme-access.index');
    Route::get('admin/audit-log', [AuditLogAdminController::class, 'index'])->name('admin.audit-log.index');
    Route::get('admin/teams', function () {
        abort_unless(request()->user()?->isTbaiAdmin(), 403);

        return redirect()->route('facilities.index', [], 301);
    })->name('admin.teams.index');
});

require __DIR__.'/settings.php';

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', RedirectProgrammeTeamUrl::class])
    ->group(function () {
        Route::middleware(EnsureTeamMembership::class)->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');
        });

        Route::get('returns/today', [DailyReturnController::class, 'edit'])->name('returns.today');
        Route::put('returns/today', [DailyReturnController::class, 'update'])->name('returns.update');
        Route::get('returns/history', FacilityReturnHistoryController::class)->name('returns.history');

        // One Facility area with tabs: Overview, Profile, Staff, Invites, Activity.
        Route::get('facility', FacilityHomeController::class)->name('facilities.home');
        Route::get('facility/profile', [FacilityController::class, 'edit'])->name('facilities.edit');
        Route::put('facility/profile', [FacilityController::class, 'update'])->name('facilities.update');
        Route::get('facility/staff', [TeamAreaController::class, 'members'])->name('facilities.members');
        Route::post('facility/staff/{user}/active', [TeamAreaController::class, 'active'])->name('facilities.members.active');
        Route::get('facility/invites', [TeamAreaController::class, 'invites'])->name('facilities.invites');
        Route::post('facility/invites/{invitation}/resend', [TeamAreaController::class, 'resend'])->name('facilities.invitations.resend');
        Route::get('facility/activity', [TeamAreaController::class, 'activity'])->name('facilities.activity');

        // Old addresses keep working. Roles and access now sits on the Staff tab.
        foreach ([
            'my-facility' => 'facilities.home',
            'profile' => 'facilities.edit',
            'members' => 'facilities.members',
            'invites' => 'facilities.invites',
            'roles' => 'facilities.members',
            'activity' => 'facilities.activity',
        ] as $legacy => $target) {
            Route::get($legacy, fn (Team $current_team) => redirect()->route($target, $current_team, 301));
        }

        Route::middleware('feature:screening')->group(function () {
            Route::get('screening/new', [ScreeningController::class, 'create'])->name('screening.create');
            Route::post('screening', [ScreeningController::class, 'store'])->name('screening.store');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

// Unknown URLs run through the web middleware so the not found page gets the
// session, shared props, and security headers like any other page.
Route::fallback(FallbackController::class);

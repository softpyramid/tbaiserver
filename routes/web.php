<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\Api\CommandApiController;
use App\Http\Controllers\Api\SuspectApiController;
use App\Http\Controllers\AuditLogAdminController;
use App\Http\Controllers\CaseNotificationAdminController;
use App\Http\Controllers\CommandCenterController;
use App\Http\Controllers\DailyReturnAdminController;
use App\Http\Controllers\DailyReturnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FacilityHomeController;
use App\Http\Controllers\FacilityReturnHistoryController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientAdminController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientTreatmentController;
use App\Http\Controllers\PopulationDenominatorAdminController;
use App\Http\Controllers\ProgrammeAdminController;
use App\Http\Controllers\ProgrammeAssignmentController;
use App\Http\Controllers\ReturnRevisionAdminController;
use App\Http\Controllers\ScreeningController;
use App\Http\Controllers\ScreeningSessionAdminController;
use App\Http\Controllers\SlidePhotoController;
use App\Http\Controllers\SuspectAssessmentAdminController;
use App\Http\Controllers\SuspectPatientLinkController;
use App\Http\Controllers\SuspectRuleSetAdminController;
use App\Http\Controllers\SuspectScreeningController;
use App\Http\Controllers\TeamAreaController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TodayShortcutController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\WebManifestController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\RedirectProgrammeTeamUrl;
use App\Http\Middleware\RejectPersonalFields;
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

Route::middleware(['auth'])->group(function () {
    Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('impersonation/{user}', [ImpersonationController::class, 'start'])
        ->whereNumber('user')
        ->name('impersonation.start');

    Route::get('today', TodayShortcutController::class)->name('returns.home');

    Route::post('programme-assignments', [ProgrammeAssignmentController::class, 'store'])->name('programme-assignments.store');

    Route::get('command', [CommandCenterController::class, 'index'])->name('command.index');
    Route::get('command/center', [CommandCenterController::class, 'center'])->name('command.center');
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
    Route::post('command/incidence/estimates', [CommandCenterController::class, 'storeIncidenceEstimate'])
        ->middleware('feature:incidence')
        ->name('command.incidence.estimates.store');
    Route::get('command/sectors/{sector}', [CommandCenterController::class, 'sector'])
        ->whereIn('sector', ['public', 'private', 'ppm'])
        ->name('command.sectors.show');
    Route::get('command/sectors/{sector}/districts/{city}', [CommandCenterController::class, 'district'])
        ->whereIn('sector', ['public', 'private', 'ppm'])
        ->name('command.districts.show');
    Route::get('command/facilities', [FacilityController::class, 'scoped'])->name('command.facilities');
    Route::get('command/returns', [CommandCenterController::class, 'returns'])->name('command.returns');
    Route::get('slide-photos/{slidePhoto}', [SlidePhotoController::class, 'show'])->name('slide-photos.show');
    Route::get('slide-photos/{slidePhoto}/thumbnail', [SlidePhotoController::class, 'thumbnail'])->name('slide-photos.thumbnail');
    Route::get('command/returns/{dailyReturn}', [CommandCenterController::class, 'showReturn'])->name('command.returns.show');

    Route::prefix('api/command')
        ->middleware(['can:viewCommandCenter', 'throttle:command-api'])
        ->name('api.command.')
        ->group(function () {
            Route::get('frame', [CommandApiController::class, 'frame'])->name('frame');
            Route::get('cumulative', [CommandApiController::class, 'cumulative'])->name('cumulative');
            Route::get('intelligence', [CommandApiController::class, 'intelligence'])->name('intelligence');
            Route::get('sectors/{sector}', [CommandApiController::class, 'sector'])
                ->whereIn('sector', ['public', 'private', 'ppm'])
                ->name('sector');
            Route::get('sectors/{sector}/districts/{city}/bmus', [CommandApiController::class, 'bmus'])
                ->whereIn('sector', ['public', 'private', 'ppm'])
                ->whereNumber('city')
                ->name('bmus');
            Route::get('map', [CommandApiController::class, 'map'])->name('map');
            Route::get('drivers', [CommandApiController::class, 'drivers'])->middleware('feature:drivers')->name('drivers');
            Route::get('ai-tests', [CommandApiController::class, 'aiTests'])->middleware('feature:ai_tests')->name('ai-tests');
            Route::get('incidence', [CommandApiController::class, 'incidence'])->middleware('feature:incidence')->name('incidence');
        });

    // Suspect-Finding screening API, Phase 1. Facility staff only; session auth.
    Route::prefix('api/v1/suspect')
        ->middleware(['feature:suspect_screening', 'throttle:command-api', RejectPersonalFields::class])
        ->name('api.suspect.')
        ->group(function () {
            Route::get('rule-set', [SuspectApiController::class, 'ruleSet'])->name('rule-set');
            Route::post('assessments', [SuspectApiController::class, 'start'])->name('assessments.store');
            Route::get('assessments/{assessment}', [SuspectApiController::class, 'show'])
                ->whereUuid('assessment')
                ->name('assessments.show');
            Route::post('assessments/{assessment}/answers', [SuspectApiController::class, 'answers'])
                ->whereUuid('assessment')
                ->name('assessments.answers');
            Route::post('assessments/{assessment}/complete', [SuspectApiController::class, 'complete'])
                ->whereUuid('assessment')
                ->name('assessments.complete');
        });

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
    Route::middleware('feature:suspect_screening')->group(function () {
        Route::get('admin/suspect-rules', [SuspectRuleSetAdminController::class, 'index'])->name('admin.suspect-rules.index');
        Route::get('admin/suspect-rules/{suspectRuleSet}', [SuspectRuleSetAdminController::class, 'show'])->name('admin.suspect-rules.show');
        Route::patch('admin/suspect-rules/{suspectRuleSet}', [SuspectRuleSetAdminController::class, 'update'])->name('admin.suspect-rules.update');
        Route::post('admin/suspect-rules/{suspectRuleSet}/clone', [SuspectRuleSetAdminController::class, 'cloneToDraft'])->name('admin.suspect-rules.clone');
        Route::post('admin/suspect-rules/{suspectRuleSet}/submit', [SuspectRuleSetAdminController::class, 'submit'])->name('admin.suspect-rules.submit');
        Route::post('admin/suspect-rules/{suspectRuleSet}/approve', [SuspectRuleSetAdminController::class, 'approve'])->name('admin.suspect-rules.approve');
        Route::post('admin/suspect-rules/{suspectRuleSet}/publish', [SuspectRuleSetAdminController::class, 'publish'])->name('admin.suspect-rules.publish');
        Route::post('admin/suspect-rules/{suspectRuleSet}/retire', [SuspectRuleSetAdminController::class, 'retire'])->name('admin.suspect-rules.retire');
        Route::get('admin/suspect-assessments', [SuspectAssessmentAdminController::class, 'index'])->name('admin.suspect-assessments.index');
    });
    Route::get('admin/patients', [PatientAdminController::class, 'index'])
        ->middleware('feature:patients')
        ->name('admin.patients.index');

    // In-app reminders (missed follow-up visits). No SMS, WhatsApp, or email.
    Route::middleware('feature:patients')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])
            ->whereUuid('notification')
            ->name('notifications.read');
    });
    Route::get('admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::patch('admin/users/{user}', [UserAdminController::class, 'update'])->name('admin.users.update');
    Route::patch('admin/memberships/{membership}', [UserAdminController::class, 'updateMembership'])->name('admin.memberships.update');
    Route::get('admin/programme-assignments', [ProgrammeAdminController::class, 'assignments'])->name('admin.programme-assignments.index');
    Route::get('admin/access-requests', [ProgrammeAdminController::class, 'accessRequests'])->name('admin.access-requests.index');
    Route::patch('admin/access-requests/{accessRequest}', [ProgrammeAdminController::class, 'updateAccessRequest'])->name('admin.access-requests.update');
    Route::get('admin/programme-access', [ProgrammeAdminController::class, 'access'])->name('admin.programme-access.index');
    Route::get('admin/audit-log', [AuditLogAdminController::class, 'index'])->name('admin.audit-log.index');
    Route::get('admin/population-denominators', [PopulationDenominatorAdminController::class, 'index'])->name('admin.population-denominators.index');
    Route::get('admin/population-denominators/create', [PopulationDenominatorAdminController::class, 'create'])->name('admin.population-denominators.create');
    Route::post('admin/population-denominators', [PopulationDenominatorAdminController::class, 'store'])->name('admin.population-denominators.store');
    Route::get('admin/population-denominators/{populationDenominator}/edit', [PopulationDenominatorAdminController::class, 'edit'])->name('admin.population-denominators.edit');
    Route::patch('admin/population-denominators/{populationDenominator}', [PopulationDenominatorAdminController::class, 'update'])->name('admin.population-denominators.update');
    Route::get('admin/population-denominators/import', [PopulationDenominatorAdminController::class, 'importForm'])->name('admin.population-denominators.import-form');
    Route::post('admin/population-denominators/import', [PopulationDenominatorAdminController::class, 'import'])->name('admin.population-denominators.import');
    Route::get('admin/case-notifications', [CaseNotificationAdminController::class, 'index'])->name('admin.case-notifications.index');
    Route::get('admin/case-notifications/import', [CaseNotificationAdminController::class, 'importForm'])->name('admin.case-notifications.import-form');
    Route::post('admin/case-notifications/import', [CaseNotificationAdminController::class, 'import'])->name('admin.case-notifications.import');
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
        Route::post('returns/{dailyReturn}/slides', [SlidePhotoController::class, 'store'])->name('returns.slides.store');
        Route::delete('slides/{slidePhoto}', [SlidePhotoController::class, 'destroy'])->name('returns.slides.destroy');

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

        Route::middleware('feature:suspect_screening')->group(function () {
            Route::get('screening/suspects', [SuspectScreeningController::class, 'index'])->name('suspect.index');
            Route::get('screening/suspects/new', [SuspectScreeningController::class, 'create'])->name('suspect.create');
            Route::get('screening/suspects/{assessment}', [SuspectScreeningController::class, 'show'])
                ->whereUuid('assessment')
                ->name('suspect.show');
            Route::post('screening/suspects/{assessment}/patient', SuspectPatientLinkController::class)
                ->middleware('feature:patients')
                ->whereUuid('assessment')
                ->name('suspect.patient.link');
        });

        // Patient records and treatment follow-up. Facility staff (frontline
        // workers included) and TB-AI admins; everyone else gets 404.
        Route::middleware('feature:patients')->group(function () {
            Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
            Route::get('patients/create', [PatientController::class, 'create'])->name('patients.create');
            Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
            Route::get('patients/{patient}', [PatientController::class, 'show'])->whereUuid('patient')->name('patients.show');
            Route::get('patients/{patient}/edit', [PatientController::class, 'edit'])->whereUuid('patient')->name('patients.edit');
            Route::put('patients/{patient}', [PatientController::class, 'update'])->whereUuid('patient')->name('patients.update');
            Route::post('patients/{patient}/treatments', [PatientTreatmentController::class, 'store'])
                ->whereUuid('patient')
                ->name('patients.treatments.store');
            Route::patch('patients/{patient}/treatments/{treatment}/outcome', [PatientTreatmentController::class, 'outcome'])
                ->whereUuid('patient')
                ->whereNumber('treatment')
                ->name('patients.treatments.outcome');
            Route::patch('patients/{patient}/visits/{visit}', [PatientTreatmentController::class, 'updateVisit'])
                ->whereUuid('patient')
                ->whereNumber('visit')
                ->name('patients.visits.update');

            // Simulated AI diagnosis demo. Same access as the patient record.
            Route::middleware('feature:ai_demo')->group(function () {
                Route::get('patients/{patient}/diagnoses/create', [DiagnosisController::class, 'create'])
                    ->whereUuid('patient')
                    ->name('patients.diagnoses.create');
                Route::post('patients/{patient}/diagnoses', [DiagnosisController::class, 'store'])
                    ->whereUuid('patient')
                    ->name('patients.diagnoses.store');
                Route::get('patients/{patient}/diagnoses/{diagnosis}', [DiagnosisController::class, 'show'])
                    ->whereUuid(['patient', 'diagnosis'])
                    ->name('patients.diagnoses.show');
                Route::get('patients/{patient}/diagnoses/{diagnosis}/image', [DiagnosisController::class, 'image'])
                    ->whereUuid(['patient', 'diagnosis'])
                    ->name('patients.diagnoses.image');
                Route::get('patients/{patient}/diagnoses/{diagnosis}/thumbnail', [DiagnosisController::class, 'thumbnail'])
                    ->whereUuid(['patient', 'diagnosis'])
                    ->name('patients.diagnoses.thumbnail');
            });
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

// Unknown URLs run through the web middleware so the not found page gets the
// session, shared props, and security headers like any other page.
Route::fallback(FallbackController::class);

<?php

use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Notifications\MissedVisit;
use App\Support\Patients\TreatmentSchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('patients');
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Karachi'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A treatment started on 2026-08-01: month 1 due 2026-09-01 (missed), month 2
 * due 2026-10-01 (missed), month 3 due 2026-11-01 (upcoming).
 */
function patTreatment(Facility $facility, string $start = '2026-08-01'): Treatment
{
    $patient = Patient::factory()->create(['facility_id' => $facility->id, 'name' => 'Test Patient Reminder', 'register_number' => 'REM-'.fake()->unique()->numerify('###')]);
    $actor = User::factory()->create();

    return app(TreatmentSchedule::class)->start($patient, [
        'start_date' => $start,
        'regimen_category' => 'category_i_new',
        'registration_type' => 'new',
        'site' => 'pulmonary',
    ], $actor);
}

/**
 * @return array<int, int>
 */
function patReminderCounts(): array
{
    return DatabaseNotification::query()
        ->where('type', MissedVisit::class)
        ->get()
        ->groupBy('notifiable_id')
        ->map->count()
        ->all();
}

test('the command flags missed visits and reminds every active facility member once', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);
    [$member] = suspectStaff(TeamRole::Member, $facility);
    [$screener] = suspectStaff(TeamRole::Screener, $facility);
    [$inactive] = suspectStaff(TeamRole::Admin, $facility);
    $inactive->forceFill(['deactivated_at' => now()])->save();
    [$elsewhere] = suspectStaff(TeamRole::Owner);
    $viewer = patViewer();
    $admin = suspectAdmin();
    $treatment = patTreatment($facility);

    $this->artisan('tbai:flag-missed-visits')
        ->expectsOutput('Flagged 2 missed visit(s), sent 6 reminder(s).')
        ->assertSuccessful();

    $visits = $treatment->visits()->get()->keyBy('month_number');
    expect($visits[1]->missed_flagged_at)->not->toBeNull()
        ->and($visits[2]->missed_flagged_at)->not->toBeNull()
        ->and($visits[3]->missed_flagged_at)->toBeNull();

    expect(patReminderCounts())->toEqualCanonicalizing([
        $owner->id => 2,
        $member->id => 2,
        $screener->id => 2,
    ]);

    foreach ([$inactive, $elsewhere, $viewer, $admin] as $nobody) {
        expect($nobody->notifications()->count())->toBe(0);
    }

    $this->artisan('tbai:flag-missed-visits')
        ->expectsOutput('Flagged 0 missed visit(s), sent 0 reminder(s).')
        ->assertSuccessful();

    expect(DatabaseNotification::query()->count())->toBe(6);
});

test('the reminder payload holds ids and dates only', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);
    $treatment = patTreatment($facility);

    $this->artisan('tbai:flag-missed-visits')->assertSuccessful();

    $notification = $owner->notifications()->orderBy('created_at')->get()->firstWhere('data.month_number', 1);
    $visit = $treatment->visits()->where('month_number', 1)->sole();

    expect($notification?->data)->toBe([
        'visit_id' => $visit->id,
        'treatment_id' => $treatment->id,
        'patient_uuid' => $treatment->patient->uuid,
        'team_slug' => $facility->team->slug,
        'month_number' => 1,
        'scheduled_date' => '2026-09-01',
    ]);

    $raw = DatabaseNotification::query()->toBase()->pluck('data')->implode(' ');
    expect($raw)->not->toContain('Test Patient Reminder')
        ->and($raw)->not->toContain($treatment->patient->register_number);
});

test('attended, cancelled, due today, future and closed-treatment visits are skipped', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);

    $attended = patTreatment($facility);
    $attended->visits()->update(['attended_date' => '2026-09-02']);

    $cancelled = patTreatment($facility);
    $cancelled->visits()->update(['cancelled_at' => now()]);

    patTreatment($facility, '2026-09-12'); // month 1 due today, 2026-10-12

    $closed = patTreatment($facility);
    $closed->update(['outcome' => 'died', 'outcome_date' => '2026-08-20']);

    $this->artisan('tbai:flag-missed-visits')
        ->expectsOutput('Flagged 0 missed visit(s), sent 0 reminder(s).')
        ->assertSuccessful();

    expect($owner->notifications()->count())->toBe(0);
});

test('the day boundary uses the Asia/Karachi date', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);
    patTreatment($facility, '2026-09-12'); // month 1 due 2026-10-12

    // 2026-10-12 23:30 UTC is already 2026-10-13 04:30 in Karachi.
    Carbon::setTestNow(Carbon::parse('2026-10-12 23:30:00', 'UTC'));

    $this->artisan('tbai:flag-missed-visits')->assertSuccessful();

    expect($owner->notifications()->count())->toBe(1);
});

test('a member who already has a reminder for a visit is not reminded again', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);
    $treatment = patTreatment($facility);

    $this->artisan('tbai:flag-missed-visits')->assertSuccessful();
    // Someone clears the flag by hand; the job must still not duplicate.
    TreatmentVisit::query()->update(['missed_flagged_at' => null]);
    $this->artisan('tbai:flag-missed-visits')->assertSuccessful();

    expect($owner->notifications()->count())->toBe(2)
        ->and($treatment->visits()->whereNotNull('missed_flagged_at')->count())->toBe(2);
});

test('the command does nothing while patients is off', function () {
    config(['tbai.features.patients' => false]);
    $facility = Facility::factory()->create();
    suspectStaff(TeamRole::Owner, $facility);
    patTreatment($facility);

    $this->artisan('tbai:flag-missed-visits')->assertSuccessful();

    expect(DatabaseNotification::query()->count())->toBe(0)
        ->and(TreatmentVisit::query()->whereNotNull('missed_flagged_at')->count())->toBe(0);
});

test('the command is scheduled daily at 06:00 Karachi time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, 'tbai:flag-missed-visits'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *')
        ->and($event->timezone)->toBe('Asia/Karachi');
});

test('the notifications page lists reminders with the patient and marks them read', function () {
    $facility = Facility::factory()->create();
    [$member] = suspectStaff(TeamRole::Member, $facility);
    $treatment = patTreatment($facility);
    $this->artisan('tbai:flag-missed-visits');

    $response = $this->actingAs($member)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('unread', 2)
            ->where('notifications.unread', 2)
            ->has('rows', 2)
            ->where('rows.0.patient', 'Test Patient Reminder ('.$treatment->patient->register_number.')')
            ->where('rows.0.href', route('patients.show', [$facility->team, $treatment->patient]))
            ->where('rows', fn ($rows) => collect($rows)->pluck('text')->contains('Month 1 follow-up visit missed (due 1 Sep 2026).')));

    $first = $response->viewData('page')['props']['rows'][0];

    $this->actingAs($member)->post($first['readUrl'])->assertRedirect();
    expect($member->unreadNotifications()->count())->toBe(1);

    $this->actingAs($member)->get(route('returns.today', $facility->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread', 1)
            ->where('nav.groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])->firstWhere('key', 'notifications')['badge'] === 1));

    $this->actingAs($member)->post(route('notifications.read-all'))->assertRedirect();
    expect($member->unreadNotifications()->count())->toBe(0);
});

test('nobody can mark another person notification read', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);
    [$other] = suspectStaff(TeamRole::Owner);
    patTreatment($facility);
    $this->artisan('tbai:flag-missed-visits');
    $id = $owner->notifications()->value('id');

    $this->actingAs($other)->post(route('notifications.read', ['notification' => $id]))->assertNotFound();
    $this->actingAs($other)->post(route('notifications.read-all'));

    expect($owner->unreadNotifications()->count())->toBe(2);
});

test('a reminder hides the patient once the person may no longer see them', function () {
    $facility = Facility::factory()->create();
    [$member] = suspectStaff(TeamRole::Member, $facility);
    patTreatment($facility);
    $this->artisan('tbai:flag-missed-visits');

    // Moved to another facility: still staff, but no longer at this one.
    $facility->team->members()->detach($member->id);
    Facility::factory()->create()->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $content = $this->actingAs($member)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.patient', null)->where('rows.0.href', null))
        ->getContent();

    expect($content)->not->toContain('Test Patient Reminder');
});

test('recording the visit as attended settles its reminders', function () {
    $facility = Facility::factory()->create();
    [$owner] = suspectStaff(TeamRole::Owner, $facility);
    [$member] = suspectStaff(TeamRole::Member, $facility);
    $treatment = patTreatment($facility);
    $this->artisan('tbai:flag-missed-visits');
    $visit = $treatment->visits()->where('month_number', 1)->sole();

    $this->actingAs($member)
        ->patch(route('patients.visits.update', [$facility->team, $treatment->patient, $visit]), ['attended_date' => '2026-09-03'])
        ->assertSessionHasNoErrors();

    expect($owner->unreadNotifications()->count())->toBe(1)
        ->and($member->unreadNotifications()->count())->toBe(1)
        ->and($owner->unreadNotifications()->first()?->data['month_number'])->toBe(2);
});

<?php

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use App\Support\ProvisionalReportedTat;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('the register date follows Asia/Karachi across midnight', function () {
    expect(config('app.timezone'))->toBe('Asia/Karachi');

    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->travelTo(CarbonImmutable::parse('2026-10-02 18:59:00', 'UTC'));

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('reportedOn', '2026-10-02'));

    $this->travelTo(CarbonImmutable::parse('2026-10-02 19:00:00', 'UTC'));

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportedOn', '2026-10-03')
            ->where('provisionalTat.label', ProvisionalReportedTat::LABEL),
        );
});

test('saving a return updates the same row and writes a revision, including backfills', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $reportedOn = now()->subDays(3)->toDateString();
    $payload = returnPayload($reportedOn);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), $payload)
        ->assertRedirect();

    $dailyReturn = DailyReturn::query()->where('facility_id', $facility->id)->first();

    expect($dailyReturn)->not->toBeNull()
        ->and(DailyReturnRevision::query()->count())->toBe(0);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), [
            ...$payload,
            'suspects' => 8,
        ])
        ->assertRedirect();

    expect(DailyReturn::query()->where('facility_id', $facility->id)->count())->toBe(1)
        ->and(DailyReturnRevision::query()->count())->toBe(1);

    $revision = DailyReturnRevision::query()->first();

    expect($revision->daily_return_id)->toBe($dailyReturn->id)
        ->and($revision->changed_by)->toBe($owner->id)
        ->and($revision->old_values['suspects'])->toBe(5)
        ->and($revision->new_values['suspects'])->toBe(8);

    $this->actingAs($owner)
        ->get(route('returns.today', ['current_team' => $facility->team, 'reported_on' => $reportedOn]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('history', 1)
            ->where('history.0.changedBy', $owner->name)
            ->where('history.0.changes.0.field', 'suspects'),
        );
});

test('technicians do not see return history and cannot backfill', function () {
    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->put(route('returns.update', $facility->team), returnPayload(now()->subDays(3)->toDateString()))
        ->assertSessionHasErrors('reported_on');

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('history', null));
});

test('a tb-ai admin can see history without being a facility member', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
    ]);
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $payload = returnPayload(now()->toDateString());

    $this->actingAs($owner)->put(route('returns.update', $facility->team), $payload);
    $this->actingAs($owner)->put(route('returns.update', $facility->team), [
        ...$payload,
        'follow_up' => 2,
    ]);

    $this->actingAs($admin)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('history', 1)
            ->where('nav.shell', 'sidebar'),
        );

    $this->actingAs($admin)
        ->put(route('returns.update', $facility->team), $payload)
        ->assertForbidden();
});

test('the form opens the return that already exists for that date', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $reportedOn = now()->subDay()->toDateString();

    $dailyReturn = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'reported_on' => $reportedOn,
        'submitted_by' => $owner->id,
        'suspects' => 9,
        'tested' => 4,
        'positive' => 1,
        'negative' => 3,
        'started_treatment' => 6,
        'follow_up' => 2,
    ]);

    $this->actingAs($owner)
        ->get(route('returns.today', ['current_team' => $facility->team, 'reported_on' => $reportedOn]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('return.suspects', 9)
            ->where('return.tested', 4)
            ->where('return.positive', 1)
            ->where('return.negative', 3)
            ->where('return.started_treatment', 6)
            ->where('return.follow_up', 2)
            ->where('facility.name', $facility->team->name)
            ->where('facility.sector', 'Public')
            ->where('facility.city', $facility->city->name)
            ->where('earliestDate', null)
            ->where('canChooseDate', true)
            ->where('latestDate', now()->toDateString())
            ->where('provisionalTat.insufficient', ProvisionalReportedTat::INSUFFICIENT)
            ->where('provisionalTat.helper', ProvisionalReportedTat::HELPER)
            ->where('lastUpdated.name', $owner->name)
            ->where('lastUpdated.at', $dailyReturn->updated_at->timezone('Asia/Karachi')->format('H:i'))
            ->where('nav.shell', 'facility'),
        );

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), [
            ...returnPayload($reportedOn),
            'suspects' => 9,
            'started_treatment' => 6,
            'follow_up' => 2,
        ])
        ->assertRedirect();

    expect(DailyReturn::query()->where('facility_id', $facility->id)->count())->toBe(1);
});

test('technicians can open today and yesterday only', function () {
    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', now()->subDay()->toDateString())
            ->where('latestDate', now()->toDateString())
            ->where('canChooseDate', false)
            ->where('todayDate', now()->toDateString())
            ->where('yesterdayDate', now()->subDay()->toDateString())
            ->where('nav.shell', 'facility'),
        );

    $this->actingAs($member)
        ->put(route('returns.update', $facility->team), returnPayload(now()->subDay()->toDateString()))
        ->assertRedirect();

    $this->actingAs($member)
        ->get(route('returns.today', [
            'current_team' => $facility->team,
            'reported_on' => now()->subDays(2)->toDateString(),
        ]))
        ->assertRedirect(route('returns.today', $facility->team))
        ->assertSessionHas('date_notice', 'Technicians can enter today or yesterday only');
});

test('senior technicians can backfill any past date', function () {
    $senior = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($senior, ['role' => TeamRole::Admin->value]);

    $this->actingAs($senior)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', null)
            ->where('canChooseDate', true),
        );

    $this->actingAs($senior)
        ->put(route('returns.update', $facility->team), returnPayload(now()->subDays(10)->toDateString()))
        ->assertRedirect();

    expect(DailyReturn::query()->count())->toBe(1);
});

test('turnaround helper stays provisional when the weighted average can be calculated', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), returnPayload(now()->toDateString()))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('provisionalTat.value', 1.5)
            ->where('provisionalTat.insufficient', null)
            ->where('provisionalTat.helper', 'Provisional until the official turnaround formula is confirmed.'),
        );
});

test('future dates stay closed', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), returnPayload(now()->addDay()->toDateString()))
        ->assertSessionHasErrors('reported_on');

    expect(DailyReturn::query()->count())->toBe(0);
});

test('tested can exceed suspects and treatment starts can exceed positives', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), [
            ...returnPayload(now()->toDateString()),
            'suspects' => 1,
            'started_treatment' => 8,
        ])
        ->assertRedirect();

    $dailyReturn = DailyReturn::query()->first();

    expect($dailyReturn->suspects)->toBe(1)
        ->and($dailyReturn->tested)->toBe(4)
        ->and($dailyReturn->started_treatment)->toBe(8);
});

test('positive and negative must add up to tested', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), [
            ...returnPayload(now()->toDateString()),
            'negative' => 0,
        ])
        ->assertSessionHasErrors([
            'tested' => 'Positive and negative must add up to tested (now 1 of 4).',
        ]);
});

/**
 * @return array<string, int|string|float>
 */
function returnPayload(string $reportedOn): array
{
    return [
        'reported_on' => $reportedOn,
        'suspects' => 5,
        'tested' => 4,
        'positive' => 1,
        'negative' => 3,
        'started_treatment' => 1,
        'follow_up' => 0,
        'median_tat_days' => 1.5,
    ];
}

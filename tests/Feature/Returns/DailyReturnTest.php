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
        ->assertInertia(fn (Assert $page) => $page->has('history', 1));

    $this->actingAs($admin)
        ->put(route('returns.update', $facility->team), $payload)
        ->assertForbidden();
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
        ->assertSessionHasErrors('tested');
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

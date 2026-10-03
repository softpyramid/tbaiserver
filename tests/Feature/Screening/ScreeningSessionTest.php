<?php

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\ScreeningSession;
use App\Models\User;
use App\Support\ScreeningScore;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => enableFeature('screening'));

test('an empty screening config explains that questions are not ready', function () {
    expect(config('screening.questions'))->toBe([])
        ->and(config('screening.cutoff'))->toBeNull();

    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->get(route('screening.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notice', ScreeningScore::NOT_READY)
            ->where('questions', [])
            ->where('screeningUrl', route('screening.create', $facility->team)),
        );

    $this->actingAs($member)
        ->post(route('screening.store', $facility->team), [
            'answers' => ['cough' => 'yes'],
        ])
        ->assertSessionHasErrors('answers');

    expect(ScreeningSession::query()->count())->toBe(0)
        ->and(DailyReturn::query()->count())->toBe(0);
});

test('a screening stores the score and suspect flag without changing the daily return', function () {
    config([
        'screening.questions' => [
            ['key' => 'cough', 'label' => 'Cough', 'points' => 2],
            ['key' => 'fever', 'label' => 'Fever', 'points' => 1],
        ],
        'screening.cutoff' => 2,
    ]);

    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'reported_on' => now()->toDateString(),
        'submitted_by' => $member->id,
        'suspects' => 3,
        'tested' => 2,
        'positive' => 1,
        'negative' => 1,
        'started_treatment' => 1,
        'follow_up' => 0,
    ]);

    $this->actingAs($member)
        ->post(route('screening.store', $facility->team), [
            'answers' => [
                'cough' => 'yes',
                'fever' => 'no',
            ],
        ])
        ->assertRedirect(route('screening.create', $facility->team))
        ->assertSessionHas('screeningConfirmation.message', 'Screening saved. Score: 2. This person is a suspect.');

    $this->actingAs($member)
        ->get(route('screening.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notice', null)
            ->has('questions', 2)
            ->where('confirmation.score', 2)
            ->where('confirmation.isSuspect', true)
            ->where('confirmation.message', 'Screening saved. Score: 2. This person is a suspect.'),
        );

    $suspect = ScreeningSession::query()->first();

    expect($suspect)->not->toBeNull()
        ->and($suspect->answers)->toBe(['cough' => 'yes', 'fever' => 'no'])
        ->and($suspect->score)->toBe(2)
        ->and($suspect->is_suspect)->toBeTrue()
        ->and($suspect->occurred_on->toDateString())->toBe(now()->toDateString());

    $this->actingAs($member)
        ->post(route('screening.store', $facility->team), [
            'answers' => [
                'cough' => 'no',
                'fever' => 'yes',
            ],
        ])
        ->assertRedirect();

    expect(ScreeningSession::query()->count())->toBe(2)
        ->and(ScreeningSession::query()->where('is_suspect', true)->count())->toBe(1)
        ->and(DailyReturn::query()->count())->toBe(1)
        ->and(DailyReturn::query()->first()->suspects)->toBe(3);

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('screeningSuspects', 1));
});

test('a screening does not accept a name, phone, or national ID', function () {
    config([
        'screening.questions' => [
            ['key' => 'cough', 'label' => 'Cough', 'points' => 1],
        ],
        'screening.cutoff' => 1,
    ]);

    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect(Schema::getColumnListing('screening_sessions'))
        ->not->toContain('name', 'phone', 'national_id')
        ->and((new ScreeningSession)->getFillable())
        ->not->toContain('name', 'phone', 'national_id');

    $this->actingAs($member)
        ->post(route('screening.store', $facility->team), [
            'name' => 'Amina Khan',
            'phone' => '03001234567',
            'national_id' => '42101-1234567-1',
            'answers' => ['cough' => 'yes'],
        ])
        ->assertSessionHasErrors(['name', 'phone', 'national_id']);

    $this->actingAs($member)
        ->post(route('screening.store', $facility->team), [
            'answers' => [
                'cough' => 'yes',
                'name' => 'Amina Khan',
            ],
        ])
        ->assertSessionHasErrors('answers.name');

    expect(ScreeningSession::query()->count())->toBe(0);
});

test('people outside the facility cannot open screening', function (string $role) {
    $user = User::factory()->create();
    $facility = Facility::factory()->create();

    if ($role === 'owner') {
        $facility->team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    } elseif ($role === 'technician') {
        $facility->team->members()->attach($user, ['role' => TeamRole::Member->value]);
    } else {
        ProgrammeAssignment::factory()->create([
            'user_id' => $user->id,
            'role' => ProgrammeRole::from($role),
        ]);
    }

    $expected = in_array($role, ['owner', 'technician'], true) ? 200 : 403;

    $this->actingAs($user)
        ->get(route('screening.create', $facility->team))
        ->assertStatus($expected);
})->with([
    'owner',
    'technician',
    'tbai_admin',
    'city_officer',
    'provincial_viewer',
    'country_viewer',
]);

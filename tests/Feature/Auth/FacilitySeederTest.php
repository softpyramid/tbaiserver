<?php

use App\Enums\TeamRole;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\Facility;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\LoginSeeder;
use RuntimeException;

test('facility seeder opens a karachi west bmu for the login accounts', function () {
    $this->app['env'] = 'local';
    config([
        'access.admin_email' => 'admin@tbai.test',
        'access.seed_login_password' => 'server-secret',
    ]);

    $this->seed(LoginSeeder::class);

    $team = Team::query()->where('slug', 'karachi-west-bmu')->first();
    $admin = User::query()->where('email', 'admin@tbai.test')->first();
    $incharge = User::query()->where('email', 'incharge@tbai.test')->first();
    $member = User::query()->where('email', 'member@tbai.test')->first();

    $team->members()->attach($admin->id, ['role' => TeamRole::Owner->value]);

    $this->seed(FacilitySeeder::class);
    $this->seed(FacilitySeeder::class);

    $today = CarbonImmutable::now('Asia/Karachi')->startOfDay();

    expect($team)->not->toBeNull()
        ->and($team->is_personal)->toBeFalse()
        ->and(Facility::query()->count())->toBe(1)
        ->and($team->facility)->not->toBeNull()
        ->and($team->facility->city->slug)->toBe('karachi-west')
        ->and($incharge->fresh()->current_team_id)->toBe($team->id)
        ->and($member->fresh()->current_team_id)->toBe($team->id)
        ->and($incharge->fresh()->ownsTeam($team))->toBeTrue()
        ->and($member->fresh()->teamRole($team))->toBe(TeamRole::Member)
        ->and($admin->fresh()->ownsTeam($team))->toBeFalse()
        ->and($admin->fresh()->isTbaiAdmin())->toBeTrue()
        ->and(DailyReturn::query()->where('facility_id', $team->facility->id)->count())->toBe(5)
        ->and(DailyReturnRevision::query()->count())->toBe(1)
        ->and(DailyReturn::query()->whereDate('reported_on', '>=', $today->toDateString())->exists())->toBeFalse();

    $revision = DailyReturnRevision::query()->first();

    expect($revision->changed_by)->toBe($incharge->id)
        ->and($revision->old_values['suspects'])->toBe(4)
        ->and($revision->new_values['suspects'])->toBe(6);

    foreach (range(1, 5) as $daysAgo) {
        expect(
            DailyReturn::query()
                ->where('facility_id', $team->facility->id)
                ->whereDate('reported_on', $today->subDays($daysAgo)->toDateString())
                ->count(),
        )->toBe(1);
    }
});

test('facility seeder requires the login accounts', function () {
    $this->app['env'] = 'local';

    expect(fn () => (new FacilitySeeder)->run())->toThrow(
        RuntimeException::class,
        'Run LoginSeeder before seeding the facility.',
    );
});

test('facility seeder does not seed sample returns in production', function () {
    $this->app['env'] = 'production';

    (new FacilitySeeder)->run();

    expect(Facility::query()->count())->toBe(0)
        ->and(DailyReturn::query()->count())->toBe(0);
});

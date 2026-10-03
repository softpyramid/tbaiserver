<?php

use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\LoginSeeder;
use RuntimeException;

test('facility seeder opens a karachi west bmu for the login accounts', function () {
    config([
        'access.admin_email' => 'admin@tbai.test',
        'access.seed_login_password' => 'server-secret',
    ]);

    $this->seed(LoginSeeder::class);
    $this->seed(FacilitySeeder::class);
    $this->seed(FacilitySeeder::class);

    $team = Team::query()->where('slug', 'karachi-west-bmu')->first();
    $admin = User::query()->where('email', 'admin@tbai.test')->first();
    $member = User::query()->where('email', 'member@tbai.test')->first();

    expect($team)->not->toBeNull()
        ->and($team->is_personal)->toBeFalse()
        ->and(Facility::query()->count())->toBe(1)
        ->and($team->facility)->not->toBeNull()
        ->and($team->facility->city->slug)->toBe('karachi-west')
        ->and($admin->fresh()->current_team_id)->toBe($team->id)
        ->and($member->fresh()->current_team_id)->toBe($team->id)
        ->and($admin->teamRole($team))->toBe(TeamRole::Owner)
        ->and($member->teamRole($team))->toBe(TeamRole::Member);
});

test('facility seeder requires the login accounts', function () {
    expect(fn () => (new FacilitySeeder)->run())->toThrow(
        RuntimeException::class,
        'Run LoginSeeder before seeding the facility.',
    );
});

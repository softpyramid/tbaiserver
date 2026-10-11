<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LoginSeeder;
use Illuminate\Support\Facades\Hash;

test('login seeder creates verified accounts that can sign in', function () {
    $this->app['env'] = 'local';
    config([
        'access.admin_email' => 'reviewer@example.com',
        'access.seed_login_password' => 'server-secret',
    ]);

    $this->seed(LoginSeeder::class);

    $admin = User::query()->where('email', 'admin@tbai.test')->first();
    $reviewer = User::query()->where('email', 'reviewer@example.com')->first();
    $member = User::query()->where('email', 'member@tbai.test')->first();
    $incharge = User::query()->where('email', 'incharge@tbai.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->isTbaiAdmin())->toBeTrue()
        ->and($admin->personalTeam())->not->toBeNull()
        ->and(Hash::check('server-secret', $admin->password))->toBeTrue()
        ->and($reviewer)->not->toBeNull()
        ->and($reviewer->isTbaiAdmin())->toBeTrue()
        ->and($member)->not->toBeNull()
        ->and($member->personalTeam())->not->toBeNull()
        ->and($incharge)->not->toBeNull();

    $this->app['env'] = 'testing';

    $this->post(route('login.store'), [
        'email' => 'reviewer@example.com',
        'password' => 'server-secret',
    ])->assertRedirect(route('facilities.index'));

    $this->assertAuthenticatedAs($reviewer);
});

test('login seeder can be run again without duplicating accounts', function () {
    $this->app['env'] = 'local';
    config(['access.seed_login_password' => 'server-secret']);

    $this->seed(LoginSeeder::class);
    $this->seed(LoginSeeder::class);

    expect(User::query()->where('email', 'admin@tbai.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'member@tbai.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'incharge@tbai.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'karachi-west@tbai.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'sindh@tbai.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'country@tbai.test')->count())->toBe(1)
        ->and(User::query()->count())->toBe(6);
});

test('login seeder wires programme scope and facility ownership', function () {
    $this->app['env'] = 'staging';
    config(['access.seed_login_password' => null]);

    $this->seed(LoginSeeder::class);

    $incharge = User::query()->where('email', 'incharge@tbai.test')->first();
    $member = User::query()->where('email', 'member@tbai.test')->first();
    $officer = User::query()->where('email', 'karachi-west@tbai.test')->first();
    $province = User::query()->where('email', 'sindh@tbai.test')->first();
    $country = User::query()->where('email', 'country@tbai.test')->first();
    $team = Team::query()->where('slug', 'karachi-west-bmu')->first();

    expect(Team::query()->where('slug', 'tb-ai-admins-team')->value('name'))->toBe('TB-AI Admin group')
        ->and(Team::query()->where('slug', 'country-viewers-team')->value('name'))->toBe('Pakistan viewers')
        ->and(Team::query()->where('slug', 'provincial-viewers-team')->value('name'))->toBe('Sindh viewers')
        ->and(Team::query()->where('slug', 'karachi-west-officers-team')->value('name'))->toBe('Karachi West officers')
        ->and($incharge)->not->toBeNull()
        ->and(Hash::check('password', $incharge->password))->toBeTrue()
        ->and($team)->not->toBeNull()
        ->and($incharge->ownsTeam($team))->toBeTrue()
        ->and($member->teamRole($team))->toBe(TeamRole::Member)
        ->and($member->ownsTeam($team))->toBeFalse();

    $pakistanId = Country::query()->where('slug', 'pakistan')->value('id');
    $sindhId = Province::query()->where('slug', 'sindh')->value('id');
    $karachiWestId = City::query()->where('slug', 'karachi-west')->value('id');

    expect(ProgrammeAssignment::query()->where('user_id', $country->id)->first())
        ->role->toBe(ProgrammeRole::CountryViewer)
        ->scope_type->toBe(ScopeType::Country)
        ->scope_id->toBe($pakistanId)
        ->and(ProgrammeAssignment::query()->where('user_id', $province->id)->first())
        ->role->toBe(ProgrammeRole::ProvincialViewer)
        ->scope_type->toBe(ScopeType::Province)
        ->scope_id->toBe($sindhId)
        ->and(ProgrammeAssignment::query()->where('user_id', $officer->id)->first())
        ->role->toBe(ProgrammeRole::CityOfficer)
        ->scope_type->toBe(ScopeType::City)
        ->scope_id->toBe($karachiWestId);

    $today = CarbonImmutable::now('Asia/Karachi')->startOfDay();

    expect(DailyReturn::query()->count())->toBeGreaterThan(100)
        ->and(DailyReturn::query()->whereDate('reported_on', '>', $today->subDay()->toDateString())->exists())->toBeFalse()
        ->and(DailyReturn::query()->whereDate('reported_on', '<', $today->subDays(60)->toDateString())->exists())->toBeFalse()
        ->and(DailyReturnRevision::query()->count())->toBe(1)
        ->and(DailyReturnRevision::query()->first()->changed_by)->toBe($incharge->id);
});

test('login seeder does not run in production', function () {
    $this->app['env'] = 'production';
    config(['access.seed_login_password' => null]);

    (new LoginSeeder)->run();

    expect(User::query()->count())->toBe(0)
        ->and(DailyReturn::query()->count())->toBe(0);
});

test('login seeder skips environments other than local, dev, and staging', function () {
    $this->app['env'] = 'testing';
    config(['access.seed_login_password' => 'password']);

    (new LoginSeeder)->run();

    expect(User::query()->count())->toBe(0);
});

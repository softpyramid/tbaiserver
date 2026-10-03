<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Country;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\ProgrammeRoleSeeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

test('geography seeder creates pakistan, sindh, and the seven pitch cities', function () {
    $this->seed(GeographySeeder::class);
    $this->seed(GeographySeeder::class);

    $pakistan = Country::query()->where('slug', 'pakistan')->first();
    $sindh = Province::query()->where('slug', 'sindh')->first();

    expect($pakistan)->not->toBeNull()
        ->and(Country::query()->count())->toBe(1)
        ->and($sindh)->not->toBeNull()
        ->and($sindh->country_id)->toBe($pakistan->id)
        ->and(Province::query()->count())->toBe(1)
        ->and(City::query()->count())->toBe(7)
        ->and(City::query()->where('slug', 'karachi-west')->value('latitude'))->toEqual('25.00000')
        ->and(City::query()->where('slug', 'thatta')->value('longitude'))->toEqual('67.92000');
});

test('programme role seeder assigns each login to its geography', function () {
    config(['access.seed_login_password' => 'server-secret']);

    $this->seed(ProgrammeRoleSeeder::class);
    $this->seed(ProgrammeRoleSeeder::class);

    $country = User::query()->where('email', 'country@tbai.test')->first();
    $province = User::query()->where('email', 'sindh@tbai.test')->first();
    $city = User::query()->where('email', 'karachi-west@tbai.test')->first();

    expect($country)->not->toBeNull()
        ->and(Hash::check('server-secret', $country->password))->toBeTrue()
        ->and($country->email_verified_at)->not->toBeNull()
        ->and(ProgrammeAssignment::query()->count())->toBe(3);

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
        ->and(ProgrammeAssignment::query()->where('user_id', $city->id)->first())
        ->role->toBe(ProgrammeRole::CityOfficer)
        ->scope_type->toBe(ScopeType::City)
        ->scope_id->toBe($karachiWestId);
});

test('programme role seeder refuses the default password outside local and testing', function () {
    $this->app['env'] = 'production';
    config(['access.seed_login_password' => null]);

    expect(fn () => (new ProgrammeRoleSeeder)->run())->toThrow(
        RuntimeException::class,
        'Set SEED_LOGIN_PASSWORD before seeding login accounts.',
    );
});

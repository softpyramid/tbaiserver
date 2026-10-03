<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Country;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Taluka;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\ProgrammeRoleSeeder;
use Illuminate\Support\Facades\Hash;

test('geography seeder creates pakistan, sindh, pitch cities, and sindh test districts', function () {
    $this->seed(GeographySeeder::class);
    $this->seed(GeographySeeder::class);

    $pakistan = Country::query()->where('slug', 'pakistan')->first();
    $sindh = Province::query()->where('slug', 'sindh')->first();

    expect($pakistan)->not->toBeNull()
        ->and(Country::query()->count())->toBe(1)
        ->and($sindh)->not->toBeNull()
        ->and($sindh->country_id)->toBe($pakistan->id)
        ->and(Province::query()->count())->toBe(3)
        ->and(City::query()->count())->toBe(13)
        ->and(City::query()->where('slug', 'karachi-west')->value('latitude'))->toEqual('25.00000')
        ->and(City::query()->where('slug', 'thatta')->value('longitude'))->toEqual('67.92000')
        ->and(Taluka::query()->count())->toBe(18);

    foreach ([
        'karachi-west' => 3,
        'karachi-east' => 3,
        'hyderabad' => 2,
        'sukkur' => 3,
        'larkana' => 2,
        'mirpurkhas' => 2,
    ] as $slug => $talukas) {
        $city = City::query()->where('slug', $slug)->first();

        expect($city)->not->toBeNull()
            ->and($city->province_id)->toBe($sindh->id)
            ->and($city->talukas()->count())->toBe($talukas);
    }
});

test('programme role seeder assigns each login to its geography', function () {
    $this->app['env'] = 'local';
    config(['access.seed_login_password' => 'server-secret']);

    $this->seed(ProgrammeRoleSeeder::class);
    $this->seed(ProgrammeRoleSeeder::class);

    $country = User::query()->where('email', 'country@tbai.test')->first();
    $province = User::query()->where('email', 'sindh@tbai.test')->first();
    $city = User::query()->where('email', 'karachi-west@tbai.test')->first();

    expect($country)->not->toBeNull()
        ->and(Hash::check('server-secret', $country->password))->toBeTrue()
        ->and($country->email_verified_at)->not->toBeNull()
        ->and(ProgrammeAssignment::query()->whereIn('role', [
            ProgrammeRole::CountryViewer,
            ProgrammeRole::ProvincialViewer,
            ProgrammeRole::CityOfficer,
        ])->count())->toBe(3);

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

test('programme role seeder does not run in production', function () {
    $this->app['env'] = 'production';
    config(['access.seed_login_password' => null]);

    (new ProgrammeRoleSeeder)->run();

    expect(User::query()->count())->toBe(0);
});

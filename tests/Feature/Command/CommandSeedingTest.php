<?php

use App\Models\User;
use Database\Seeders\CommandPrototypeSeeder;
use Illuminate\Support\Facades\DB;

function commandSeededTotals(): array
{
    $returns = DB::table('daily_returns')
        ->selectRaw('SUM(suspects) as suspects, SUM(tested) as tested, SUM(positive) as positive, SUM(negative) as negative, SUM(started_treatment) as started, COUNT(*) as rows')
        ->first();

    return [
        'suspects' => (int) $returns->suspects,
        'tested' => (int) $returns->tested,
        'positive' => (int) $returns->positive,
        'negative' => (int) $returns->negative,
        'started' => (int) $returns->started,
        'rows' => (int) $returns->rows,
        'facilities' => DB::table('facilities')->count(),
        'bmus' => DB::table('facilities')->where('kind', 'bmu')->count(),
        'mdr' => (int) DB::table('mdr_rr_cases')->sum('cases'),
        'ai' => (int) DB::table('ai_test_counts')->sum('tests'),
        'outcomes' => DB::table('treatment_outcomes')->count(),
    ];
}

test('command seeder reproduces the prototype programme headline exactly and is idempotent', function () {
    $this->app['env'] = 'local';
    User::factory()->create(['email' => 'admin@tbai.test']);

    $this->seed(CommandPrototypeSeeder::class);
    $first = commandSeededTotals();

    expect($first['suspects'])->toBe(236271)
        ->and($first['tested'])->toBe(211103)
        ->and($first['positive'])->toBe(30737)
        ->and($first['negative'])->toBe(180366)
        ->and($first['started'])->toBe(28004)
        ->and($first['mdr'])->toBe(931)
        ->and($first['ai'])->toBe(66826)
        ->and($first['bmus'])->toBe(143)
        ->and(DB::table('facilities')->where('sector', 'public')->count())->toBe(163)
        ->and(DB::table('facilities')->where('sector', 'private')->count())->toBe(128)
        ->and(DB::table('facilities')->where('sector', 'ppm')->count())->toBe(124)
        ->and(DB::table('cities')->join('provinces', 'provinces.id', '=', 'cities.province_id')->where('provinces.slug', 'sindh')->count())->toBe(29)
        ->and(DB::table('ai_test_fixtures')->count())->toBe(4)
        ->and(DB::table('incidence_targets')->count())->toBe(5)
        ->and(DB::table('district_indicators')->count())->toBe(29)
        ->and(DB::table('sector_indicators')->count())->toBe(87);

    $this->seed(CommandPrototypeSeeder::class);

    expect(commandSeededTotals())->toBe($first);
});

test('command seeder never writes in production or any other environment', function (string $environment) {
    $this->app['env'] = $environment;
    User::factory()->create(['email' => 'admin@tbai.test']);

    $this->artisan('db:seed', ['--class' => CommandPrototypeSeeder::class, '--force' => true])->assertSuccessful();

    foreach ([
        'facilities', 'daily_returns', 'cities', 'treatment_outcomes', 'mdr_rr_cases', 'ai_test_counts',
        'contact_tracing', 'hiv_testing', 'district_indicators', 'incidence_targets', 'incidence_estimates', 'ai_test_fixtures', 'sector_indicators',
    ] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
})->with(['production', 'testing', 'prod']);

test('command center flags default on in local, dev and staging, off in production and testing, and an explicit value wins', function () {
    $config = fn (string $env, array $vars): array => json_decode((string) shell_exec(
        'cd '.escapeshellarg(base_path()).' && '
        .collect($vars)->map(fn ($v, $k) => $k.'='.escapeshellarg($v))->implode(' ')
        .' APP_ENV='.escapeshellarg($env)
        .' php -r \'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(config("tbai.features"));\''
    ), true);
    $four = fn (array $features): array => [$features['drivers'], $features['interventions'], $features['incidence'], $features['ai_tests']];

    $local = $config('local', []);
    $dev = $config('dev', []);
    $staging = $config('staging', []);
    $production = $config('production', []);
    $testing = $config('testing', []);
    $unlisted = $config('qa', []);
    $override = $config('production', ['TBAI_SHOW_DRIVERS' => 'true']);
    $on = $config('production', ['TBAI_SHOW_DRIVERS' => 'true', 'TBAI_SHOW_AI_TESTS' => 'true']);
    $off = $config('staging', ['TBAI_SHOW_DRIVERS' => 'false', 'TBAI_SHOW_INCIDENCE' => 'false', 'TBAI_SHOW_AI_TESTS' => 'false']);
    $interventions = $config('staging', ['TBAI_SHOW_DRIVERS' => 'true', 'TBAI_SHOW_INTERVENTIONS' => 'false']);
    $followsDrivers = $config('staging', ['TBAI_SHOW_DRIVERS' => 'false']);

    expect(config('tbai.area_defaults'))->toBe(['local' => true, 'dev' => true, 'staging' => true, 'production' => false])
        ->and($four($local))->toBe([true, true, true, true])
        ->and($four($dev))->toBe([true, true, true, true])
        ->and($four($staging))->toBe([true, true, true, true])
        ->and($four($production))->toBe([false, false, false, false])
        ->and($four($testing))->toBe([false, false, false, false])
        ->and($four($unlisted))->toBe([false, false, false, false])
        ->and($four($override))->toBe([true, true, false, false])
        ->and($four($on))->toBe([true, true, false, true])
        ->and($four($off))->toBe([false, false, false, false])
        ->and([$interventions['drivers'], $interventions['interventions']])->toBe([true, false])
        ->and([$followsDrivers['drivers'], $followsDrivers['interventions']])->toBe([false, false]);
});

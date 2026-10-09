<?php

use App\Enums\AreaType;
use App\Enums\PopulationStatus;
use App\Models\PopulationDenominator;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\CommandPrototypeSeeder;
use Database\Seeders\PopulationDenominatorSeeder;

test('population denominator seeder confirms the sindh census and marks every district as a placeholder', function () {
    $this->app['env'] = 'local';
    User::factory()->create(['email' => 'admin@tbai.test']);

    $this->seed(CommandPrototypeSeeder::class);
    $this->seed(PopulationDenominatorSeeder::class);

    expect(PopulationDenominator::query()->count())->toBe(30)
        ->and(PopulationDenominator::query()->where('area_type', AreaType::Province)->count())->toBe(1)
        ->and(PopulationDenominator::query()->where('area_type', AreaType::District)->count())->toBe(29)
        ->and(PopulationDenominator::query()->where('status', PopulationStatus::Confirmed)->count())->toBe(1)
        ->and(PopulationDenominator::query()->where('status', PopulationStatus::Placeholder)->count())->toBe(29);

    $sindh = Province::query()->where('slug', 'sindh')->firstOrFail();
    $provinceRow = PopulationDenominator::query()
        ->where('area_type', AreaType::Province)
        ->where('area_id', $sindh->id)
        ->where('year', 2023)
        ->sole();

    expect($provinceRow->population)->toBe(55696147)
        ->and($provinceRow->status)->toBe(PopulationStatus::Confirmed)
        ->and($provinceRow->source)->toBe('PBS Census 2023')
        ->and($provinceRow->source_year)->toBe(2023)
        ->and($provinceRow->reference_url)->toContain('pbs.gov.pk');
});

test('population denominator seeder is idempotent and never overwrites admin figures', function () {
    $this->app['env'] = 'local';
    User::factory()->create(['email' => 'admin@tbai.test']);

    $this->seed(CommandPrototypeSeeder::class);
    $this->seed(PopulationDenominatorSeeder::class);

    $district = PopulationDenominator::query()->where('area_type', AreaType::District)->firstOrFail();
    $district->update([
        'population' => 1234567,
        'status' => PopulationStatus::Confirmed,
        'reference_url' => null,
    ]);

    $this->seed(PopulationDenominatorSeeder::class);

    expect(PopulationDenominator::query()->count())->toBe(30);

    $district->refresh();

    expect($district->population)->toBe(1234567)
        ->and($district->status)->toBe(PopulationStatus::Confirmed)
        ->and($district->reference_url)->toBeNull();
});

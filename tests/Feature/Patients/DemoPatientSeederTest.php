<?php

use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPatientSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\LoginSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Karachi'));
});

afterEach(fn () => Carbon::setTestNow());

test('the demo patient seeder is idempotent and leaves one overdue visit', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed(LoginSeeder::class);

    $this->seed(DemoPatientSeeder::class);
    $this->seed(DemoPatientSeeder::class);

    expect(Patient::query()->count())->toBe(2)
        ->and(Treatment::query()->count())->toBe(1)
        ->and(TreatmentVisit::query()->count())->toBe(6);

    $one = Patient::query()->where('register_number', 'DEMO-0001')->sole();
    $two = Patient::query()->where('register_number', 'DEMO-0002')->sole();

    expect($one->name)->toBe('Demo Patient One')
        ->and($one->cnic)->toBe('0000000000001')
        ->and($one->phone)->toBe('+923000000001')
        ->and($one->facility->team->slug)->toBe('karachi-west-bmu')
        ->and($two->name)->toBe('Demo Patient Two')
        ->and($two->cnic)->toBeNull()
        ->and($two->age)->toBe(12);

    // Start 2026-08-07: month 1 (2026-09-07) attended, month 2 (2026-10-07) overdue.
    $statuses = Treatment::query()->sole()->visits->map(fn (TreatmentVisit $visit) => $visit->statusOn('2026-10-12'))->all();

    expect($statuses)->toBe(['attended', 'missed', 'upcoming', 'upcoming', 'upcoming', 'upcoming']);
});

test('the demo patient seeder keeps edits on a second run', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed(LoginSeeder::class);
    $this->seed(DemoPatientSeeder::class);

    Patient::query()->where('register_number', 'DEMO-0001')->sole()->update(['age' => 40]);
    $this->seed(DemoPatientSeeder::class);

    expect(Patient::query()->where('register_number', 'DEMO-0001')->sole()->age)->toBe(40);
});

test('the demo patient seeder skips production', function () {
    app()->detectEnvironment(fn () => 'production');
    (new DemoPatientSeeder)->run();

    expect(Patient::query()->count())->toBe(0);
});

test('DatabaseSeeder adds demo patients in local only', function (string $environment, int $patients) {
    app()->detectEnvironment(fn () => $environment);
    $this->seed(LoginSeeder::class);
    $this->seed(FacilitySeeder::class);

    $seeder = Mockery::mock(DatabaseSeeder::class)->makePartial();
    $seeder->shouldReceive('call')->andReturnUsing(function ($class) use ($seeder) {
        $classes = is_array($class) ? $class : [$class];

        foreach ($classes as $name) {
            if ($name === DemoPatientSeeder::class) {
                app($name)->run();
            }
        }

        return $seeder;
    });
    $seeder->run();

    expect(Patient::query()->count())->toBe($patients);
})->with([
    ['local', 2],
    ['staging', 0],
    ['testing', 0],
]);

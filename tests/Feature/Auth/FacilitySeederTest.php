<?php

use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\Facility;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\LoginSeeder;
use DateTimeInterface;
use RuntimeException;

test('facility seeder opens sindh test sites for the login accounts', function () {
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
    $windowStart = $today->subDays(FacilitySeeder::WINDOW_DAYS);
    $yesterday = $today->subDay();

    expect($team)->not->toBeNull()
        ->and($team->is_personal)->toBeFalse()
        ->and($team->name)->toBe('Karachi West BMU')
        ->and(Facility::query()->count())->toBe(15)
        ->and($team->facility)->not->toBeNull()
        ->and($team->facility->city->slug)->toBe('karachi-west')
        ->and($team->facility->taluka)->not->toBeNull()
        ->and($team->facility->taluka->slug)->toBe('orangi')
        ->and($incharge->fresh()->current_team_id)->toBe($team->id)
        ->and($member->fresh()->current_team_id)->toBe($team->id)
        ->and($incharge->fresh()->ownsTeam($team))->toBeTrue()
        ->and($member->fresh()->teamRole($team))->toBe(TeamRole::Member)
        ->and($admin->fresh()->ownsTeam($team))->toBeFalse()
        ->and($admin->fresh()->isTbaiAdmin())->toBeTrue()
        ->and(DailyReturn::query()->whereDate('reported_on', '>=', $today->toDateString())->exists())->toBeFalse();

    $revision = DailyReturnRevision::query()->first();

    expect(DailyReturnRevision::query()->count())->toBe(1)
        ->and($revision->changed_by)->toBe($incharge->id)
        ->and($revision->new_values['suspects'])->toBe($revision->old_values['suspects'] + 2);

    $counts = Facility::query()
        ->with('city')
        ->get()
        ->countBy(fn (Facility $facility): string => $facility->city->slug);

    expect($counts)->toHaveCount(6)
        ->and($counts->get('karachi-west'))->toBe(3)
        ->and($counts->get('karachi-east'))->toBe(3)
        ->and($counts->get('hyderabad'))->toBe(2)
        ->and($counts->get('sukkur'))->toBe(3)
        ->and($counts->get('larkana'))->toBe(2)
        ->and($counts->get('mirpurkhas'))->toBe(2);

    $sectors = Facility::query()
        ->pluck('sector')
        ->map(fn (Sector $sector): string => $sector->value)
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($sectors)->toBe(['ppm', 'private', 'public']);

    Facility::query()->with('taluka')->each(function (Facility $facility): void {
        expect($facility->taluka)->not->toBeNull()
            ->and($facility->taluka->city_id)->toBe($facility->city_id);
    });

    Team::query()
        ->where('is_personal', false)
        ->where('slug', '!=', 'karachi-west-bmu')
        ->pluck('name')
        ->each(function (string $name): void {
            expect($name)->toStartWith('Test ');
        });

    $gulshan = Team::query()->where('slug', FacilitySeeder::RISING_POSITIVITY_SLUG)->first();

    expect($incharge->ownsTeam($gulshan))->toBeFalse()
        ->and($member->belongsToTeam($gulshan))->toBeFalse();

    $window = [];

    for ($cursor = $windowStart; $cursor->lessThanOrEqualTo($yesterday); $cursor = $cursor->addDay()) {
        $window[] = $cursor->toDateString();
    }

    expect($window)->toHaveCount(FacilitySeeder::WINDOW_DAYS);

    Facility::query()->each(function (Facility $facility) use ($window): void {
        $dates = DailyReturn::query()
            ->where('facility_id', $facility->id)
            ->pluck('reported_on')
            ->map(fn (mixed $date): string => $date instanceof DateTimeInterface
                ? $date->format('Y-m-d')
                : substr((string) $date, 0, 10));

        $missing = collect($window)->diff($dates);

        expect($dates->count())->toBeLessThan(FacilitySeeder::WINDOW_DAYS)
            ->and($missing)->not->toBeEmpty()
            ->and($dates->diff($window))->toBeEmpty()
            ->and($dates->count() + $missing->count())->toBe(FacilitySeeder::WINDOW_DAYS);
    });

    DailyReturn::query()->each(function (DailyReturn $return): void {
        expect($return->positive + $return->negative)->toBe($return->tested)
            ->and($return->positive * 100)->toBeGreaterThanOrEqual($return->tested * 6)
            ->and($return->positive * 100)->toBeLessThanOrEqual($return->tested * 25)
            ->and($return->started_treatment)->toBeLessThanOrEqual($return->positive);
    });

    $west = DailyReturn::query()->where('facility_id', $team->facility->id)->get();
    $lagging = $west->filter(fn (DailyReturn $return): bool => $return->started_treatment < $return->positive)->count();
    $weekend = [];
    $weekday = [];

    foreach ($west as $return) {
        $day = CarbonImmutable::parse($return->reported_on, 'Asia/Karachi')->dayOfWeek;

        if ($day === CarbonImmutable::SATURDAY || $day === CarbonImmutable::SUNDAY) {
            $weekend[] = $return->tested;
        } else {
            $weekday[] = $return->tested;
        }
    }

    expect($lagging)->toBeGreaterThan($west->count() / 2)
        ->and($weekend)->not->toBeEmpty()
        ->and($weekday)->not->toBeEmpty()
        ->and(array_sum($weekend) / count($weekend))->toBeLessThan(array_sum($weekday) / count($weekday));

    $rising = DailyReturn::query()
        ->where('facility_id', $gulshan->facility->id)
        ->orderBy('reported_on')
        ->get();
    $early = $rising->take(10);
    $late = $rising->slice(-10)->values();

    expect($late->sum('positive') / $late->sum('tested'))
        ->toBeGreaterThan($early->sum('positive') / $early->sum('tested') + 0.08);

    $gapTeam = Team::query()->where('slug', FacilitySeeder::TREATMENT_GAP_SLUG)->first();
    $gap = DailyReturn::query()->where('facility_id', $gapTeam->facility->id)->get();

    expect($gap->sum('started_treatment') * 3)->toBeLessThan($gap->sum('positive'));

    $returnCount = DailyReturn::query()->count();
    $facilityCount = Facility::query()->count();
    $revisionCount = DailyReturnRevision::query()->count();

    $this->seed(FacilitySeeder::class);

    expect(DailyReturn::query()->count())->toBe($returnCount)
        ->and(Facility::query()->count())->toBe($facilityCount)
        ->and(DailyReturnRevision::query()->count())->toBe($revisionCount);

    $duplicates = DailyReturn::query()
        ->select('facility_id')
        ->selectRaw('reported_on')
        ->selectRaw('count(*) as aggregate')
        ->groupBy('facility_id', 'reported_on')
        ->having('aggregate', '>', 1)
        ->get();

    expect($duplicates)->toHaveCount(0);
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

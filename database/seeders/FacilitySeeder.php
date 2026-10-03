<?php

namespace Database\Seeders;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\Taluka;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SkipsOutsideLocalDevStaging;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FacilitySeeder extends Seeder
{
    use SkipsOutsideLocalDevStaging;

    /**
     * Karachi East public BMU. Positivity climbs across the seeded window.
     */
    public const RISING_POSITIVITY_SLUG = 'test-gulshan-bmu';

    /**
     * Hyderabad public BMU. Treatment starts stay well behind positives.
     */
    public const TREATMENT_GAP_SLUG = 'test-latifabad-bmu';

    /**
     * Calendar days requested per facility, ending yesterday in Asia/Karachi.
     */
    public const WINDOW_DAYS = 60;

    /**
     * Skip about one day in eight so the series has gaps, not zero rows.
     */
    private const GAP_EVERY = 8;

    /**
     * Seed Sindh test facilities and 60 days of daily returns.
     *
     * Test data. Karachi West BMU stays with the in-charge and technician
     * accounts. Other sites are named as test facilities. There is no notes
     * column on daily returns, so this class comment marks the rows rather
     * than a patient identifier. Safe to run again. Updates an existing
     * seeded facility in place and does not insert a second return for the
     * same facility and date. Does not delete other facilities or returns.
     * Skips production and every environment other than local, dev, and staging.
     *
     * The in-charge account owns Karachi West BMU. The member account stays a
     * technician there. The TB-AI admin keeps that programme role.
     *
     *   php artisan db:seed --class=FacilitySeeder
     */
    public function run(): void
    {
        if ($this->skipsOutsideLocalDevStaging()) {
            return;
        }

        $incharge = $this->user('incharge@tbai.test', 'LoginSeeder');
        $member = $this->user('member@tbai.test', 'LoginSeeder');

        $this->call(GeographySeeder::class);

        $sites = [];

        foreach ($this->facilities() as $ordinal => $definition) {
            $sites[] = [
                'facility' => $this->seedFacility($definition, $incharge, $member),
                'ordinal' => $ordinal,
                'series' => $definition['series'],
                'owned' => $definition['owned'],
            ];
        }

        $this->seedDailyReturns($sites, $incharge);

        $this->command?->info('Test facilities ready: '.count($sites).' sites. Sindh, plus test sites in Lahore and Peshawar.');
        $this->command?->line('  In-charge: '.$incharge->email.' owns Karachi West BMU.');
        $this->command?->line('  Technician: '.$member->email.' at Karachi West BMU.');
        $this->command?->line('  Test data: 60-day daily returns with gaps. Rising positivity: Test Gulshan BMU. Treatment gap: Test Latifabad BMU.');
    }

    private function adminEmail(): string
    {
        $adminEmail = config('access.admin_email');
        $adminEmail = is_string($adminEmail) ? strtolower(trim($adminEmail)) : '';

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return 'admin@tbai.test';
        }

        return $adminEmail;
    }

    private function user(string $email, string $seeder): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new RuntimeException("Run {$seeder} before seeding the facility. Missing {$email}.");
        }

        return $user;
    }

    private function ensureMember(Team $team, User $user, TeamRole $role): void
    {
        $existing = $team->members()->whereKey($user->id)->first();

        if ($existing === null) {
            $team->members()->attach($user->id, [
                'role' => $role->value,
            ]);

            return;
        }

        $current = $existing->pivot->role ?? null;
        $currentValue = $current instanceof TeamRole ? $current->value : (string) $current;

        if ($currentValue !== $role->value) {
            $team->members()->updateExistingPivot($user->id, [
                'role' => $role->value,
            ]);
        }
    }

    /**
     * The seeded admin used to own this facility. Move that membership off
     * without removing the TB-AI admin programme role.
     */
    private function releaseSeededAdminOwnership(Team $team, User $incharge): void
    {
        $emails = array_unique(['admin@tbai.test', $this->adminEmail()]);

        foreach ($emails as $email) {
            $admin = User::query()->where('email', $email)->first();

            if ($admin === null || $admin->is($incharge) || ! $admin->ownsTeam($team)) {
                continue;
            }

            $personal = $admin->personalTeam();

            if ($personal !== null && $admin->current_team_id === $team->id) {
                $admin->switchTeam($personal);
            }

            $team->members()->detach($admin->id);

            if ($admin->current_team_id === $team->id) {
                $admin->forceFill(['current_team_id' => $personal?->id])->save();
            }
        }
    }

    /**
     * @param  array{
     *     slug: string,
     *     name: string,
     *     city: string,
     *     taluka: string,
     *     sector: Sector,
     *     kind: FacilityKind,
     *     latitude: float,
     *     longitude: float,
     *     series: 'standard'|'rising'|'gap',
     *     owned: bool
     * }  $definition
     */
    private function seedFacility(array $definition, User $incharge, User $member): Facility
    {
        $city = City::query()->where('slug', $definition['city'])->first();

        if ($city === null) {
            throw new RuntimeException("Missing city [{$definition['city']}] for facility seeding.");
        }

        $taluka = Taluka::query()
            ->where('city_id', $city->id)
            ->where('slug', Str::slug($definition['taluka']))
            ->first();

        if ($taluka === null) {
            throw new RuntimeException("Missing taluka [{$definition['taluka']}] for facility seeding.");
        }

        $team = Team::query()->firstOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'is_personal' => false,
            ],
        );

        $attributes = [
            'city_id' => $city->id,
            'taluka_id' => $taluka->id,
            'sector' => $definition['sector'],
            'kind' => $definition['kind'],
            'status' => FacilityStatus::Active,
            'latitude' => $definition['latitude'],
            'longitude' => $definition['longitude'],
        ];

        if (! $definition['owned']) {
            $attributes['code'] = $definition['slug'];
        }

        $facility = Facility::query()->firstOrCreate(
            ['team_id' => $team->id],
            $attributes,
        );

        if ((int) $facility->city_id !== (int) $city->id || (int) $facility->taluka_id !== (int) $taluka->id) {
            $facility->forceFill([
                'city_id' => $city->id,
                'taluka_id' => $taluka->id,
            ])->save();
        }

        if ($definition['owned']) {
            $this->ensureMember($team, $incharge, TeamRole::Owner);
            $this->ensureMember($team, $member, TeamRole::Member);
            $this->releaseSeededAdminOwnership($team, $incharge);

            $incharge->switchTeam($team);
            $member->switchTeam($team);
        }

        return $facility;
    }

    /**
     * Test data. Sixty Asia/Karachi days ending yesterday, per facility.
     * Some dates are omitted so the series has gaps. A second run inserts
     * only missing facility and date pairs. The newest new Karachi West row
     * is saved, then updated, so DailyReturn writes one revision.
     *
     * @param  list<array{facility: Facility, ordinal: int, series: 'standard'|'rising'|'gap', owned: bool}>  $sites
     */
    private function seedDailyReturns(array $sites, User $editor): void
    {
        $today = CarbonImmutable::now('Asia/Karachi')->startOfDay();
        $facilityIds = array_map(fn (array $site): int => $site['facility']->id, $sites);
        $existing = $this->existingReturnKeys($facilityIds);
        $now = now()->toDateTimeString();
        $rows = [];
        $edit = null;

        foreach ($sites as $site) {
            for ($daysAgo = self::WINDOW_DAYS; $daysAgo >= 1; $daysAgo--) {
                $dayIndex = self::WINDOW_DAYS - $daysAgo;

                if (($dayIndex + $site['ordinal']) % self::GAP_EVERY === 0) {
                    continue;
                }

                $reportedOn = $today->subDays($daysAgo)->toDateString();
                $key = $site['facility']->id.'|'.$reportedOn;

                if (isset($existing[$key])) {
                    continue;
                }

                $counts = $this->counts($dayIndex, $site['ordinal'], $reportedOn, $site['series']);

                $rows[] = [
                    'facility_id' => $site['facility']->id,
                    'reported_on' => $reportedOn,
                    'submitted_by' => $editor->id,
                    'suspects' => $counts['suspects'],
                    'tested' => $counts['tested'],
                    'positive' => $counts['positive'],
                    'negative' => $counts['negative'],
                    'started_treatment' => $counts['started_treatment'],
                    'follow_up' => $counts['follow_up'],
                    'median_tat_days' => $counts['median_tat_days'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($site['owned'] && ($edit === null || $reportedOn > $edit['reported_on'])) {
                    $edit = [
                        'facility_id' => $site['facility']->id,
                        'reported_on' => $reportedOn,
                        'suspects' => $counts['suspects'],
                    ];
                }
            }
        }

        DB::transaction(function () use ($rows, $edit, $editor): void {
            foreach (array_chunk($rows, 40) as $chunk) {
                DailyReturn::query()->insert($chunk);
            }

            $this->editNewestReturn($edit, $editor);
        });
    }

    /**
     * @param  list<int>  $facilityIds
     * @return array<string, true>
     */
    private function existingReturnKeys(array $facilityIds): array
    {
        $existing = [];

        DailyReturn::query()
            ->whereIn('facility_id', $facilityIds)
            ->select(['facility_id', 'reported_on'])
            ->each(function (DailyReturn $return) use (&$existing): void {
                $existing[$return->facility_id.'|'.$this->reportedOnKey($return->getAttribute('reported_on'))] = true;
            });

        return $existing;
    }

    private function reportedOnKey(mixed $reportedOn): string
    {
        if ($reportedOn instanceof DateTimeInterface) {
            return $reportedOn->format('Y-m-d');
        }

        return substr((string) $reportedOn, 0, 10);
    }

    /**
     * @param  array{facility_id: int, reported_on: string, suspects: int}|null  $edit
     */
    private function editNewestReturn(?array $edit, User $editor): void
    {
        if ($edit === null) {
            return;
        }

        $return = DailyReturn::query()
            ->where('facility_id', $edit['facility_id'])
            ->whereDate('reported_on', $edit['reported_on'])
            ->first();

        if ($return === null) {
            return;
        }

        $guard = Auth::guard();
        $guard->setUser($editor);

        try {
            $return->update(['suspects' => $edit['suspects'] + 2]);
        } finally {
            $guard->forgetUser();
        }
    }

    /**
     * @param  'standard'|'rising'|'gap'  $series
     * @return array{
     *     suspects: int,
     *     tested: int,
     *     positive: int,
     *     negative: int,
     *     started_treatment: int,
     *     follow_up: int,
     *     median_tat_days: string
     * }
     */
    private function counts(int $dayIndex, int $ordinal, string $reportedOn, string $series): array
    {
        $date = CarbonImmutable::parse($reportedOn, 'Asia/Karachi');
        $weekend = in_array($date->dayOfWeek, [CarbonImmutable::SATURDAY, CarbonImmutable::SUNDAY], true);
        $swing = ($dayIndex * 3 + $ordinal * 5) % 11;
        $tested = $weekend ? 10 + $swing : 32 + ($swing * 2);
        $basisPoints = $this->positivityBasisPoints($dayIndex, $ordinal, $series);
        $positive = (int) round($tested * $basisPoints / 10000);
        $minimum = intdiv($tested * 6 + 99, 100);
        $maximum = intdiv($tested * 25, 100);

        if ($minimum > $maximum) {
            throw new RuntimeException('Seeded tested volume cannot hold positivity between 6 and 25 percent.');
        }

        $positive = max($minimum, min($maximum, $positive));
        $negative = $tested - $positive;
        $started = $this->startedTreatment($positive, $dayIndex, $series);

        if ($positive + $negative !== $tested || $started > $positive) {
            throw new RuntimeException('Seeded daily return broke the tested total.');
        }

        if ($positive * 100 < $tested * 6 || $positive * 100 > $tested * 25) {
            throw new RuntimeException('Seeded positivity is outside 6 to 25 percent.');
        }

        return [
            'suspects' => $tested + 3 + ($dayIndex % 4),
            'tested' => $tested,
            'positive' => $positive,
            'negative' => $negative,
            'started_treatment' => $started,
            'follow_up' => $dayIndex % 3,
            'median_tat_days' => number_format(1 + ($dayIndex % 5) * 0.5, 2, '.', ''),
        ];
    }

    /**
     * @param  'standard'|'rising'|'gap'  $series
     */
    private function positivityBasisPoints(int $dayIndex, int $ordinal, string $series): int
    {
        return match ($series) {
            'rising' => 600 + (int) round($dayIndex * 1800 / (self::WINDOW_DAYS - 1)),
            'gap', 'standard' => 1000 + (($dayIndex + $ordinal) % 8) * 100,
        };
    }

    /**
     * @param  'standard'|'rising'|'gap'  $series
     */
    private function startedTreatment(int $positive, int $dayIndex, string $series): int
    {
        if ($series === 'gap') {
            return intdiv($positive, 5);
        }

        if ($positive === 0 || $dayIndex % 3 === 1) {
            return $positive;
        }

        return max(0, $positive - 1 - ($dayIndex % 2));
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     city: string,
     *     taluka: string,
     *     sector: Sector,
     *     kind: FacilityKind,
     *     latitude: float,
     *     longitude: float,
     *     series: 'standard'|'rising'|'gap',
     *     owned: bool
     * }>
     */
    private function facilities(): array
    {
        return [
            [
                'slug' => 'karachi-west-bmu',
                'name' => 'Karachi West BMU',
                'city' => 'karachi-west',
                'taluka' => 'Orangi',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 25.0,
                'longitude' => 66.9,
                'series' => 'standard',
                'owned' => true,
            ],
            [
                'slug' => 'test-baldia-clinic',
                'name' => 'Test Baldia Clinic',
                'city' => 'karachi-west',
                'taluka' => 'Baldia',
                'sector' => Sector::Private,
                'kind' => FacilityKind::Clinic,
                'latitude' => 24.97,
                'longitude' => 66.97,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-site-ppm-lab',
                'name' => 'Test SITE PPM Lab',
                'city' => 'karachi-west',
                'taluka' => 'SITE',
                'sector' => Sector::Ppm,
                'kind' => FacilityKind::Hospital,
                'latitude' => 25.03,
                'longitude' => 66.85,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => self::RISING_POSITIVITY_SLUG,
                'name' => 'Test Gulshan BMU',
                'city' => 'karachi-east',
                'taluka' => 'Gulshan-e-Iqbal',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 24.92,
                'longitude' => 67.08,
                'series' => 'rising',
                'owned' => false,
            ],
            [
                'slug' => 'test-jamshed-clinic',
                'name' => 'Test Jamshed Clinic',
                'city' => 'karachi-east',
                'taluka' => 'Jamshed',
                'sector' => Sector::Private,
                'kind' => FacilityKind::Clinic,
                'latitude' => 24.86,
                'longitude' => 67.03,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-ferozabad-ppm',
                'name' => 'Test Ferozabad PPM',
                'city' => 'karachi-east',
                'taluka' => 'Ferozabad',
                'sector' => Sector::Ppm,
                'kind' => FacilityKind::Hospital,
                'latitude' => 24.88,
                'longitude' => 67.05,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => self::TREATMENT_GAP_SLUG,
                'name' => 'Test Latifabad BMU',
                'city' => 'hyderabad',
                'taluka' => 'Latifabad',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 25.38,
                'longitude' => 68.35,
                'series' => 'gap',
                'owned' => false,
            ],
            [
                'slug' => 'test-qasimabad-clinic',
                'name' => 'Test Qasimabad Clinic',
                'city' => 'hyderabad',
                'taluka' => 'Qasimabad',
                'sector' => Sector::Private,
                'kind' => FacilityKind::Clinic,
                'latitude' => 25.41,
                'longitude' => 68.34,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-sukkur-city-bmu',
                'name' => 'Test Sukkur City BMU',
                'city' => 'sukkur',
                'taluka' => 'Sukkur City',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 27.71,
                'longitude' => 68.85,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-rohri-clinic',
                'name' => 'Test Rohri Clinic',
                'city' => 'sukkur',
                'taluka' => 'Rohri',
                'sector' => Sector::Private,
                'kind' => FacilityKind::Clinic,
                'latitude' => 27.69,
                'longitude' => 68.9,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-new-sukkur-ppm',
                'name' => 'Test New Sukkur PPM',
                'city' => 'sukkur',
                'taluka' => 'New Sukkur',
                'sector' => Sector::Ppm,
                'kind' => FacilityKind::Hospital,
                'latitude' => 27.72,
                'longitude' => 68.87,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-ratodero-bmu',
                'name' => 'Test Ratodero BMU',
                'city' => 'larkana',
                'taluka' => 'Ratodero',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 27.8,
                'longitude' => 68.29,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-dokri-ppm',
                'name' => 'Test Dokri PPM',
                'city' => 'larkana',
                'taluka' => 'Dokri',
                'sector' => Sector::Ppm,
                'kind' => FacilityKind::Clinic,
                'latitude' => 27.41,
                'longitude' => 68.13,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-digri-bmu',
                'name' => 'Test Digri BMU',
                'city' => 'mirpurkhas',
                'taluka' => 'Digri',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 25.15,
                'longitude' => 69.1,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-hussain-bux-mari-clinic',
                'name' => 'Test Hussain Bux Mari Clinic',
                'city' => 'mirpurkhas',
                'taluka' => 'Hussain Bux Mari',
                'sector' => Sector::Private,
                'kind' => FacilityKind::Clinic,
                'latitude' => 25.53,
                'longitude' => 69.01,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-lahore-bmu',
                'name' => 'Test Lahore BMU',
                'city' => 'lahore',
                'taluka' => 'Lahore City',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 31.52,
                'longitude' => 74.36,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-lahore-clinic',
                'name' => 'Test Lahore Clinic',
                'city' => 'lahore',
                'taluka' => 'Gulberg',
                'sector' => Sector::Private,
                'kind' => FacilityKind::Clinic,
                'latitude' => 31.51,
                'longitude' => 74.34,
                'series' => 'standard',
                'owned' => false,
            ],
            [
                'slug' => 'test-peshawar-bmu',
                'name' => 'Test Peshawar BMU',
                'city' => 'peshawar',
                'taluka' => 'Peshawar City',
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'latitude' => 34.01,
                'longitude' => 71.58,
                'series' => 'standard',
                'owned' => false,
            ],
        ];
    }
}

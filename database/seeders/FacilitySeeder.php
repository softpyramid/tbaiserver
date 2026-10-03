<?php

namespace Database\Seeders;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SkipsOutsideLocalDevStaging;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class FacilitySeeder extends Seeder
{
    use SkipsOutsideLocalDevStaging;

    /**
     * Seed one public BMU so today's register can be opened.
     *
     * Test data. Also seeds five recent daily returns for Karachi West BMU.
     * There is no notes column, so these rows are marked in this class
     * rather than with a patient identifier. Safe to run again. Does not
     * delete other facilities or returns. Skips production and every
     * environment other than local, dev, and staging.
     *
     * The in-charge account owns the facility. The member account stays a
     * technician. The TB-AI admin keeps that programme role.
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

        $city = City::query()->where('slug', 'karachi-west')->first();

        if ($city === null) {
            throw new RuntimeException('Missing city [karachi-west] for facility seeding.');
        }

        $team = Team::query()->firstOrCreate(
            ['slug' => 'karachi-west-bmu'],
            [
                'name' => 'Karachi West BMU',
                'is_personal' => false,
            ],
        );

        $facility = Facility::query()->firstOrCreate(
            ['team_id' => $team->id],
            [
                'city_id' => $city->id,
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'status' => FacilityStatus::Active,
                'latitude' => 25.0,
                'longitude' => 66.9,
            ],
        );

        $this->ensureMember($team, $incharge, TeamRole::Owner);
        $this->ensureMember($team, $member, TeamRole::Member);
        $this->releaseSeededAdminOwnership($team, $incharge);

        $incharge->switchTeam($team);
        $member->switchTeam($team);

        $this->seedSampleReturns($facility, $incharge);

        $this->command?->info('Facility ready: Karachi West BMU <karachi-west-bmu>.');
        $this->command?->line('  In-charge: '.$incharge->email);
        $this->command?->line('  Technician: '.$member->email);
        $this->command?->line('  Test data: 5 daily returns, one with a revision.');
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
     * Test data. Five past days in Asia/Karachi, one row per facility and date.
     * The newest of those days is saved, then updated, so DailyReturn writes
     * one revision. The editor is the facility in-charge.
     */
    private function seedSampleReturns(Facility $facility, User $editor): void
    {
        $today = CarbonImmutable::now('Asia/Karachi')->startOfDay();

        foreach ($this->sampleDays() as $daysAgo => $sample) {
            $reportedOn = $today->subDays($daysAgo)->toDateString();

            $exists = DailyReturn::query()
                ->where('facility_id', $facility->id)
                ->whereDate('reported_on', $reportedOn)
                ->exists();

            if ($exists) {
                continue;
            }

            $return = DailyReturn::query()->create([
                'facility_id' => $facility->id,
                'reported_on' => $reportedOn,
                'submitted_by' => $editor->id,
                'suspects' => $sample['suspects'],
                'tested' => 4,
                'positive' => 1,
                'negative' => 3,
                'started_treatment' => 1,
                'follow_up' => 0,
                'median_tat_days' => '2.00',
            ]);

            if (! $sample['edited']) {
                continue;
            }

            $guard = Auth::guard();
            $guard->setUser($editor);

            try {
                $return->update(['suspects' => $sample['suspects_after_edit']]);
            } finally {
                $guard->forgetUser();
            }
        }
    }

    /**
     * @return array<int, array{suspects: int, suspects_after_edit: int, edited: bool}>
     */
    private function sampleDays(): array
    {
        return [
            1 => ['suspects' => 4, 'suspects_after_edit' => 6, 'edited' => true],
            2 => ['suspects' => 5, 'suspects_after_edit' => 5, 'edited' => false],
            3 => ['suspects' => 7, 'suspects_after_edit' => 7, 'edited' => false],
            4 => ['suspects' => 8, 'suspects_after_edit' => 8, 'edited' => false],
            5 => ['suspects' => 9, 'suspects_after_edit' => 9, 'edited' => false],
        ];
    }
}

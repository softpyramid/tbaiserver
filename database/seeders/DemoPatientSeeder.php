<?php

namespace Database\Seeders;

use App\Enums\DiseaseSite;
use App\Enums\PatientGender;
use App\Enums\RegimenCategory;
use App\Enums\RegistrationType;
use App\Enums\SputumResult;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Team;
use App\Models\User;
use App\Support\EntryDate;
use App\Support\Patients\TreatmentSchedule;
use Database\Seeders\Concerns\SkipsOutsideLocalDevStaging;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoPatientSeeder extends Seeder
{
    use SkipsOutsideLocalDevStaging;

    public const TEAM_SLUG = 'karachi-west-bmu';

    /**
     * Two obviously fake demo patients at Karachi West BMU (no real person):
     * "Demo Patient One" on a category I treatment that started 2 months
     * and 5 days ago (month 1 attended, month 2 overdue, the rest upcoming)
     * and "Demo Patient Two", a child with no CNIC, phone or treatment.
     *
     * Idempotent: a patient is created only when its register number is
     * missing at the facility, and a treatment only when the patient has
     * none, so admin edits stay. DatabaseSeeder runs it in local only;
     * staging runs it explicitly:
     *
     *   php artisan db:seed --class=DemoPatientSeeder
     */
    public function run(): void
    {
        if ($this->skipsOutsideLocalDevStaging()) {
            return;
        }

        $team = Team::query()->where('slug', self::TEAM_SLUG)->whereHas('facility')->first();
        $facility = $team?->facility;

        if (! $facility instanceof Facility) {
            throw new RuntimeException('Run FacilitySeeder before DemoPatientSeeder. Missing Karachi West BMU.');
        }

        $actor = User::query()->where('email', 'incharge@tbai.test')->first()
            ?? $facility->team->members()->first();

        $one = $this->patient($facility, $actor, 'DEMO-0001', [
            'name' => 'Demo Patient One',
            'cnic' => '0000000000001',
            'phone' => '+923000000001',
            'gender' => PatientGender::Male,
            'age' => 34,
        ]);

        $this->patient($facility, $actor, 'DEMO-0002', [
            'name' => 'Demo Patient Two',
            'cnic' => null,
            'phone' => null,
            'gender' => PatientGender::Female,
            'age' => 12,
        ]);

        if ($actor instanceof User && ! $one->treatments()->exists()) {
            $start = EntryDate::now()->subMonthsNoOverflow(2)->subDays(5);

            $treatment = app(TreatmentSchedule::class)->start($one, [
                'start_date' => $start->toDateString(),
                'regimen_category' => RegimenCategory::CategoryINew->value,
                'registration_type' => RegistrationType::New->value,
                'site' => DiseaseSite::Pulmonary->value,
                'tb03_number' => 'DEMO-TB03-1',
                'notes' => 'Demo treatment for testing. Not a real patient.',
            ], $actor);

            $treatment->visits()->where('month_number', 1)->update([
                'attended_date' => $start->addMonthsNoOverflow(1)->toDateString(),
                'sputum_result' => SputumResult::Negative->value,
            ]);
        }
    }

    /**
     * @param  array{name: string, cnic: string|null, phone: string|null, gender: PatientGender, age: int}  $attributes
     */
    private function patient(Facility $facility, ?User $actor, string $register, array $attributes): Patient
    {
        $existing = Patient::query()
            ->where('facility_id', $facility->id)
            ->where('register_number', $register)
            ->first();

        if ($existing instanceof Patient) {
            return $existing;
        }

        return Patient::query()->create([
            ...$attributes,
            'facility_id' => $facility->id,
            'register_number' => $register,
            'informed_of_data_use' => true,
            'informed_at' => now(),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Notifications\MissedVisit;
use App\Support\EntryDate;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Flags follow-up visits that were due before today (programme time zone)
 * and not attended, on an active treatment, and sends one in-app reminder
 * per visit to each active member of the facility who can screen. Safe to
 * run again: a flagged visit is skipped, and nobody gets a second reminder
 * for the same visit.
 */
class FlagMissedVisits extends Command
{
    protected $signature = 'tbai:flag-missed-visits';

    protected $description = 'Flag missed treatment follow-up visits and send in-app reminders to the facility staff.';

    public function handle(): int
    {
        if (! config('tbai.features.patients')) {
            $this->info('Patients is switched off. Nothing to do.');

            return self::SUCCESS;
        }

        $today = EntryDate::now()->toDateString();
        $roles = array_map(
            fn (TeamRole $role): string => $role->value,
            array_values(array_filter(TeamRole::cases(), fn (TeamRole $role): bool => $role->canScreen())),
        );

        $visits = 0;
        $sent = 0;

        TreatmentVisit::query()
            ->whereDate('scheduled_date', '<', $today)
            ->whereNull('attended_date')
            ->whereNull('cancelled_at')
            ->whereNull('missed_flagged_at')
            ->whereHas('treatment', fn ($query) => $query->whereNull('outcome'))
            ->with('treatment.patient.facility.team')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($roles, &$visits, &$sent): void {
                foreach ($chunk as $visit) {
                    /** @var TreatmentVisit $visit */
                    $patient = $visit->treatment->patient;
                    $team = $patient->facility->team;

                    foreach ($this->recipients($team, $roles) as $user) {
                        if ($this->alreadyReminded($user, $visit)) {
                            continue;
                        }

                        $user->notify(new MissedVisit($visit, $team, $patient->uuid));
                        $sent++;
                    }

                    $visit->forceFill(['missed_flagged_at' => now()])->save();
                    $visits++;
                }
            });

        $this->info("Flagged {$visits} missed visit(s), sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $roles
     * @return iterable<User>
     */
    private function recipients(Team $team, array $roles): iterable
    {
        return $team->members()
            ->wherePivotIn('role', $roles)
            ->whereNull('users.deactivated_at')
            ->get();
    }

    private function alreadyReminded(User $user, TreatmentVisit $visit): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->where('type', MissedVisit::class)
            ->where('data->visit_id', $visit->id)
            ->exists();
    }
}

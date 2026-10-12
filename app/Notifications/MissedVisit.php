<?php

namespace App\Notifications;

use App\Models\Team;
use App\Models\TreatmentVisit;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder that a monthly follow-up visit was missed. Stored in the
 * database only (no SMS, WhatsApp or email). The payload holds ids and
 * dates only, never the patient's name, CNIC or phone; the notifications
 * page reads those from the patient for a user who may still see them.
 */
class MissedVisit extends Notification
{
    public function __construct(
        private readonly TreatmentVisit $visit,
        private readonly Team $team,
        private readonly string $patientUuid,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'visit_id' => $this->visit->id,
            'treatment_id' => $this->visit->treatment_id,
            'patient_uuid' => $this->patientUuid,
            'team_slug' => $this->team->slug,
            'month_number' => $this->visit->month_number,
            'scheduled_date' => $this->visit->scheduled_date->toDateString(),
        ];
    }
}

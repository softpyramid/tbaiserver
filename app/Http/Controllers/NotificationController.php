<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Notifications\MissedVisit;
use App\Support\EntryDate;
use App\Support\Patients\PatientAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's in-app reminders. The stored payload holds ids and
 * dates only; the patient's name and register number are added here, and
 * only while the person may still see that patient. Facility staff and
 * TB-AI admins only; programme viewers get 403.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly PatientAccess $access) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $notifications = $user->notifications()->limit(100)->get();

        $uuids = $notifications
            ->map(fn (DatabaseNotification $notification): mixed => $notification->data['patient_uuid'] ?? null)
            ->filter(fn (mixed $uuid): bool => is_string($uuid))
            ->unique()
            ->values()
            ->all();

        $patients = Patient::query()
            ->whereIn('uuid', $uuids)
            ->with('facility.team')
            ->get()
            ->filter(fn (Patient $patient): bool => $this->access->canSee($user, $patient))
            ->keyBy('uuid');

        return Inertia::render('notifications/index', [
            'unread' => $user->unreadNotifications()->count(),
            'readAllUrl' => route('notifications.read-all'),
            'rows' => $notifications->map(function (DatabaseNotification $notification) use ($patients): array {
                $data = $notification->data;
                $patient = $patients->get($data['patient_uuid'] ?? '');

                return [
                    'id' => $notification->id,
                    'read' => $notification->read_at !== null,
                    'date' => $notification->created_at === null ? '' : EntryDate::dateTime($notification->created_at),
                    'text' => $notification->type === MissedVisit::class
                        ? __('entry.notifications_missed_visit', [
                            'month' => $data['month_number'] ?? '',
                            'date' => EntryDate::display($data['scheduled_date'] ?? ''),
                        ])
                        : __('entry.notifications_generic'),
                    'patient' => $patient instanceof Patient
                        ? $patient->name.' ('.$patient->register_number.')'
                        : null,
                    'facility' => $patient instanceof Patient ? $patient->facility->team->name : null,
                    'href' => $patient instanceof Patient
                        ? route('patients.show', ['current_team' => $patient->facility->team->slug, 'patient' => $patient->uuid])
                        : null,
                    'readUrl' => route('notifications.read', ['notification' => $notification->id]),
                ];
            })->values()->all(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $row = $this->user($request)->notifications()->whereKey($notification)->first();
        abort_unless($row instanceof DatabaseNotification, 404);

        $row->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->isTbaiAdmin() || $this->access->isStaffAnywhere($user), 403);

        return $user;
    }
}

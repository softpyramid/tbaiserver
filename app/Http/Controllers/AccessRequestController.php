<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessRequest;
use App\Models\AccessRequest;
use App\Notifications\AccessRequestReceived;
use App\Policies\AccessRequestPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestController extends Controller
{
    public const string RECEIVED_STATUS = 'We have your request. We will review it and be in touch.';

    /**
     * Session flag for a completed submit. It is not flash data, so a refresh
     * or a later visit in this session still shows the success panel.
     */
    public const string RECEIVED_SESSION_KEY = 'access_request_received';

    /**
     * Show the request-access form, or the success panel after a submit.
     *
     * Stored requests are never passed to the page. Any status flash is
     * cleared here so it cannot appear beside the form on a later load.
     */
    public function create(Request $request): Response
    {
        $request->session()->forget('status');

        $received = $request->session()->get(self::RECEIVED_SESSION_KEY) === true;

        return Inertia::render('access-request', [
            'received' => $received,
            'status' => $received ? self::RECEIVED_STATUS : null,
            'defaults' => $received ? null : $this->previousInput($request),
        ]);
    }

    /**
     * Store a request for later review. This does not create a user.
     */
    public function store(StoreAccessRequest $request): RedirectResponse
    {
        if ($request->filled(StoreAccessRequest::HONEYPOT)) {
            return $this->received();
        }

        $accessRequest = AccessRequest::query()->create($request->safe()->only([
            'name',
            'role',
            'organisation',
            'district',
            'email',
        ]));

        $adminEmail = AccessRequestPolicy::configuredAdministratorEmail();

        if ($adminEmail !== null) {
            Notification::route('mail', $adminEmail)
                ->notify(new AccessRequestReceived($accessRequest));
        }

        return $this->received();
    }

    /**
     * Confirm receipt without revealing whether the submission was stored.
     */
    private function received(): RedirectResponse
    {
        $session = request()->session();
        $session->forget('status');
        $session->put(self::RECEIVED_SESSION_KEY, true);

        return to_route('access-requests.create');
    }

    /**
     * Values to put back into the form after validation fails.
     *
     * @return array<string, string>|null
     */
    private function previousInput(Request $request): ?array
    {
        $previous = [];

        foreach (['name', 'role', 'organisation', 'district', 'email', 'consent'] as $field) {
            $value = $request->old($field);

            if (is_string($value) && $value !== '') {
                $previous[$field] = $value;
            }
        }

        return $previous === [] ? null : $previous;
    }
}

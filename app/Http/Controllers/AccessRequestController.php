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
     * Show the request-access form.
     *
     * Stored requests are never passed to the page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('access-request', [
            'status' => $request->session()->get('status'),
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
        return to_route('access-requests.create')->with('status', self::RECEIVED_STATUS);
    }
}

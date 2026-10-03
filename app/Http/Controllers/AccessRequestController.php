<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessRequest;
use App\Models\AccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestController extends Controller
{
    /**
     * Show the request-access form.
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
        AccessRequest::query()->create($request->validated());

        return to_route('access-requests.create')->with(
            'status',
            'We have your request. We will review it and be in touch.',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Http\Requests\StoreProgrammeAssignmentRequest;
use App\Models\ProgrammeAssignment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProgrammeAssignmentController extends Controller
{
    /**
     * Store a programme role scoped to a country, province, or city.
     */
    public function store(StoreProgrammeAssignmentRequest $request): RedirectResponse
    {
        $role = ProgrammeRole::from($request->string('role')->toString());
        $scopeType = $request->input('scope_type');

        ProgrammeAssignment::query()->create([
            'user_id' => $request->integer('user_id'),
            'role' => $role,
            'scope_type' => is_string($scopeType) && $scopeType !== '' ? ScopeType::from($scopeType) : null,
            'scope_id' => $role->scopeType() === null ? null : $request->integer('scope_id'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Programme role saved.')]);

        return back();
    }
}

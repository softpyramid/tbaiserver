<?php

namespace App\Http\Requests\Teams;

use App\Enums\FrontlineDesignation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\UniqueTeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateTeamInvitationRequest extends FormRequest
{
    /**
     * Inviting at all needs the invite permission. A senior technician who
     * asks for a senior technician role is refused here too; an in-charge
     * role is refused for everyone by the rules below.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');
        $user = $this->user();

        if (! $team instanceof Team || $user === null || ! Gate::forUser($user)->allows('inviteMember', $team)) {
            return false;
        }

        $role = TeamRole::tryFrom((string) $this->input('role'));

        return $role !== TeamRole::Admin || Gate::forUser($user)->allows('inviteAs', [$team, $role]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueTeamInvitation($team)],
            // Nobody invites an in-charge here. Ownership transfer is out of scope.
            'role' => ['required', 'string', Rule::enum(TeamRole::class)->except([TeamRole::Owner])],
            // Frontline workers need a designation; no other role takes one.
            'designation' => [
                'nullable',
                'string',
                'required_if:role,'.TeamRole::Screener->value,
                'prohibited_unless:role,'.TeamRole::Screener->value,
                Rule::enum(FrontlineDesignation::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'designation.required_if' => __('entry.designation_required'),
            'designation.prohibited_unless' => __('entry.designation_only_frontline'),
        ];
    }
}

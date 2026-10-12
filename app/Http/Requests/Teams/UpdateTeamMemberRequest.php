<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Support\MemberRoleChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The role is a team role, or "screener:<designation>" from the roster
     * picker. A frontline worker needs a designation; other roles take none.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'max:40'],
            'designation' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function choice(): MemberRoleChoice
    {
        return MemberRoleChoice::fromInput($this->input('role'), $this->input('designation'), $this->allowedRoles());
    }

    /**
     * @return list<TeamRole>
     */
    private function allowedRoles(): array
    {
        $roles = array_values(array_map(
            fn (array $option): TeamRole => TeamRole::from($option['value']),
            TeamRole::assignable(),
        ));

        $team = $this->route('team');

        if ($team instanceof Team && $team->facility()->exists()) {
            $roles[] = TeamRole::Screener;
        }

        return $roles;
    }
}

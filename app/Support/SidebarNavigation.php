<?php

namespace App\Support;

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

class SidebarNavigation
{
    /**
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, href: string}>}>
     */
    public function groups(?User $user, ?Team $facilityTeam): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isTbaiAdmin()) {
            return [
                $this->group('platform', __('entry.nav_group_platform'), [
                    $this->item('command', __('entry.nav_command'), route('command.index')),
                    $this->item('command_map', __('entry.command_map'), route('command.map')),
                    $this->item('command_alerts', __('entry.command_alerts'), route('command.alerts')),
                    $this->item('command_drivers', __('entry.drivers_title'), route('command.drivers')),
                    $this->item('command_incidence', __('entry.incidence_title'), route('command.incidence')),
                ]),
                $this->group('administration', __('entry.nav_group_administration'), [
                    $this->item('facilities', __('entry.nav_facilities'), route('facilities.index')),
                    $this->item('daily_returns', __('entry.nav_daily_returns'), route('admin.daily-returns.index')),
                    $this->item('revisions', __('entry.nav_revisions'), route('admin.revisions.index')),
                    $this->item('screening_sessions', __('entry.nav_screening_sessions'), route('admin.screening-sessions.index')),
                    $this->item('users', __('entry.nav_users'), route('admin.users.index')),
                    $this->item('programme_assignments', __('entry.nav_programme_assignments'), route('admin.programme-assignments.index')),
                    $this->item('access_requests', __('entry.nav_access_requests'), route('admin.access-requests.index')),
                    $this->item('teams', __('entry.nav_teams'), route('admin.teams.index')),
                ]),
            ];
        }

        if ($user->programmeAssignments()->exists()) {
            return [
                $this->group('platform', __('entry.nav_group_platform'), [
                    $this->item('command', __('entry.nav_command'), route('command.index')),
                    $this->item('command_map', __('entry.command_map'), route('command.map')),
                    $this->item('command_alerts', __('entry.command_alerts'), route('command.alerts')),
                    $this->item('command_drivers', __('entry.drivers_title'), route('command.drivers')),
                    $this->item('command_incidence', __('entry.incidence_title'), route('command.incidence')),
                    $this->item('facilities', $this->facilitiesLabel($user), route('command.facilities')),
                ]),
            ];
        }

        if ($facilityTeam === null || ! $user->belongsToTeam($facilityTeam)) {
            return [];
        }

        $role = $user->teamRole($facilityTeam);
        $items = [
            $this->item('register', __('entry.nav_register_long'), route('returns.today', $facilityTeam)),
        ];

        if ($role === TeamRole::Owner) {
            $items[] = $this->item('returns_history', __('entry.nav_returns_history'), route('returns.history', $facilityTeam));
        }

        $items[] = $this->item('screening', __('entry.nav_screening'), route('screening.create', $facilityTeam));
        $items[] = $this->item('my_facility', __('entry.nav_facility'), route('facilities.home', $facilityTeam));

        if ($role === TeamRole::Owner) {
            $items[] = $this->item('facility_profile', __('entry.nav_facility_long'), route('facilities.edit', $facilityTeam));
            $items[] = $this->item('team_members', __('entry.nav_team_members'), route('facilities.members', $facilityTeam));
        }

        return [
            $this->group('platform', __('entry.nav_group_platform'), $items),
        ];
    }

    private function facilitiesLabel(User $user): string
    {
        $roles = $user->programmeAssignments()->pluck('role')->map(
            fn (mixed $role): string => $role instanceof ProgrammeRole ? $role->value : (string) $role,
        );

        if ($roles->contains(ProgrammeRole::CountryViewer->value)) {
            return __('entry.nav_facilities');
        }

        if ($roles->contains(ProgrammeRole::ProvincialViewer->value)) {
            return __('entry.nav_facilities_province');
        }

        return __('entry.nav_facilities_city');
    }

    /**
     * @param  list<array{key: string, label: string, href: string}>  $items
     * @return array{key: string, label: string, items: list<array{key: string, label: string, href: string}>}
     */
    private function group(string $key, string $label, array $items): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'items' => $items,
        ];
    }

    /**
     * @return array{key: string, label: string, href: string}
     */
    private function item(string $key, string $label, string $href): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
        ];
    }
}

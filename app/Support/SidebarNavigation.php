<?php

namespace App\Support;

use App\Enums\AccessRequestStatus;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\Team;
use App\Models\User;

class SidebarNavigation
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         href: string|null,
     *         badge: int|null,
     *         expanded: bool,
     *         children: list<array{key: string, label: string, href: string, badge: int|null}>
     *     }>
     * }>
     */
    public function groups(?User $user, ?Team $facilityTeam): array
    {
        if ($user === null) {
            return [];
        }

        $groups = $this->sections($user, $facilityTeam);

        return $this->markExpanded($groups, $this->currentPath());
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         href: string|null,
     *         badge: int|null,
     *         expanded: bool,
     *         children: list<array{key: string, label: string, href: string, badge: int|null}>
     *     }>
     * }>
     */
    private function sections(User $user, ?Team $facilityTeam): array
    {
        if ($user->isTbaiAdmin()) {
            return [
                $this->group('command', __('entry.nav_group_command'), $this->commandItems()),
                $this->group('administration', __('entry.nav_group_administration'), $this->administrationItems()),
            ];
        }

        if ($user->programmeAssignments()->exists()) {
            return [
                $this->group('command', __('entry.nav_group_command'), $this->commandItems()),
            ];
        }

        if ($facilityTeam === null || ! $user->belongsToTeam($facilityTeam)) {
            return [];
        }

        return [
            $this->group('facility', __('entry.nav_group_my_facility'), $this->facilityItems($user, $facilityTeam)),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }>
     */
    private function commandItems(): array
    {
        return [
            $this->link('command', __('entry.cumulative_title'), route('command.index')),
            $this->parent('registered', __('entry.nav_registered'), [
                $this->child('public', Sector::Public->label(), route('command.sectors.show', ['sector' => Sector::Public->value])),
                $this->child('private', Sector::Private->label(), route('command.sectors.show', ['sector' => Sector::Private->value])),
                $this->child('ppm', Sector::Ppm->label(), route('command.sectors.show', ['sector' => Sector::Ppm->value])),
            ]),
            $this->link('command_map', __('entry.command_map'), route('command.map')),
            $this->link('command_alerts', __('entry.command_alerts'), route('command.alerts')),
            $this->link('command_drivers', __('entry.nav_drivers'), route('command.drivers')),
            $this->link('command_incidence', __('entry.incidence_title'), route('command.incidence')),
            $this->link('daily_returns', __('entry.nav_daily_returns'), route('command.returns')),
            $this->link('account', __('entry.nav_account'), route('profile.edit')),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }>
     */
    private function administrationItems(): array
    {
        $pending = AccessRequest::query()
            ->where('status', AccessRequestStatus::Pending)
            ->count();
        $badge = $pending > 0 ? $pending : null;

        return [
            $this->parent('facilities', __('entry.nav_facilities'), [
                $this->child('all_facilities', __('entry.nav_all_facilities'), route('facilities.index')),
                $this->child('add_facility', __('entry.nav_add_facility'), route('facilities.create')),
            ]),
            $this->parent('data', __('entry.nav_data'), [
                $this->child('daily_returns', __('entry.nav_daily_returns'), route('admin.daily-returns.index')),
                $this->child('revisions', __('entry.nav_revisions'), route('admin.revisions.index')),
                $this->child('screening_sessions', __('entry.nav_screening_sessions'), route('admin.screening-sessions.index')),
            ]),
            $this->parent('people', __('entry.nav_people'), [
                $this->child('users', __('entry.nav_users'), route('admin.users.index')),
                $this->child('programme_assignments', __('entry.nav_programme_assignments'), route('admin.programme-assignments.index')),
                $this->child('access_requests', __('entry.nav_access_requests'), route('admin.access-requests.index'), $badge),
                $this->child('teams', __('entry.nav_teams'), route('admin.teams.index')),
            ]),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }>
     */
    private function facilityItems(User $user, Team $facilityTeam): array
    {
        $role = $user->teamRole($facilityTeam);
        $items = [
            $this->link('register', __('entry.nav_register_long'), route('returns.today', $facilityTeam)),
        ];

        if ($role === TeamRole::Owner) {
            $items[] = $this->link('returns_history', __('entry.nav_returns_history'), route('returns.history', $facilityTeam));
        }

        $items[] = $this->link('screening', __('entry.nav_screening'), route('screening.create', $facilityTeam));
        $items[] = $this->link('my_facility', __('entry.nav_facility'), route('facilities.home', $facilityTeam));

        if ($role === TeamRole::Owner || $role === TeamRole::Admin) {
            $items[] = $this->link('facility_profile', __('entry.nav_facility_long'), route('facilities.edit', $facilityTeam));
            $items[] = $this->link('team_members', __('entry.nav_team_members'), route('facilities.members', $facilityTeam));
        }

        $items[] = $this->link('account', __('entry.nav_account'), route('profile.edit'));

        return $items;
    }

    /**
     * @param  list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }>  $items
     * @return array{
     *     key: string,
     *     label: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         href: string|null,
     *         badge: int|null,
     *         expanded: bool,
     *         children: list<array{key: string, label: string, href: string, badge: int|null}>
     *     }>
     * }
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
     * @return array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }
     */
    private function link(string $key, string $label, string $href): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'badge' => null,
            'expanded' => false,
            'children' => [],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, href: string, badge: int|null}>  $children
     * @return array{
     *     key: string,
     *     label: string,
     *     href: null,
     *     badge: int|null,
     *     expanded: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }
     */
    private function parent(string $key, string $label, array $children): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => null,
            'badge' => null,
            'expanded' => false,
            'children' => $children,
        ];
    }

    /**
     * @return array{key: string, label: string, href: string, badge: int|null}
     */
    private function child(string $key, string $label, string $href, ?int $badge = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'badge' => $badge,
        ];
    }

    /**
     * @param  list<array{
     *     key: string,
     *     label: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         href: string|null,
     *         badge: int|null,
     *         expanded: bool,
     *         children: list<array{key: string, label: string, href: string, badge: int|null}>
     *     }>
     * }>  $groups
     * @return list<array{
     *     key: string,
     *     label: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         href: string|null,
     *         badge: int|null,
     *         expanded: bool,
     *         children: list<array{key: string, label: string, href: string, badge: int|null}>
     *     }>
     * }>
     */
    private function markExpanded(array $groups, string $current): array
    {
        foreach ($groups as $groupIndex => $group) {
            foreach ($group['items'] as $itemIndex => $item) {
                $open = false;

                foreach ($item['children'] as $child) {
                    if ($this->matches($child['href'], $current)) {
                        $open = true;
                        break;
                    }
                }

                $groups[$groupIndex]['items'][$itemIndex]['expanded'] = $open;
            }
        }

        return $groups;
    }

    private function currentPath(): string
    {
        $path = '/'.ltrim(request()->path(), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function matches(string $href, string $current): bool
    {
        $path = parse_url($href, PHP_URL_PATH);
        $target = is_string($path) ? $path : $href;
        $target = $target === '/' ? '/' : rtrim($target, '/');

        return $current === $target || str_starts_with($current, $target.'/');
    }
}

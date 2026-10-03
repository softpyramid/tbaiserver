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
     *         current: bool,
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
     * The command center has one sidebar link for the TB-AI admin, so the
     * pages under it (Overview, Registered, Map, Alerts) are reached with a tab
     * strip on the page itself. Everyone else has those in the sidebar.
     *
     * @return list<array{id: string, label: string, href: string, current: bool, badge: int|null}>
     */
    public function commandAreaTabs(?User $user): array
    {
        $name = request()->route()?->getName();

        if ($user === null || ! $user->isTbaiAdmin() || $name === null || ! str_starts_with($name, 'command.')) {
            return [];
        }

        $rows = [
            ['overview', __('entry.nav_overview'), route('command.index'), $name === 'command.index'],
            ['registered', __('entry.nav_registered'), route('command.sectors.show', ['sector' => Sector::Public->value]), str_starts_with($name, 'command.sectors.') || str_starts_with($name, 'command.districts.')],
            ['map', __('entry.command_map'), route('command.map'), $name === 'command.map'],
            ['alerts', __('entry.command_alerts'), route('command.alerts'), str_starts_with($name, 'command.alerts')],
        ];

        return array_map(fn (array $row): array => [
            'id' => $row[0],
            'label' => $row[1],
            'href' => $row[2],
            'current' => $row[3],
            'badge' => null,
        ], $rows);
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
     *         current: bool,
     *         children: list<array{key: string, label: string, href: string, badge: int|null}>
     *     }>
     * }>
     */
    private function sections(User $user, ?Team $facilityTeam): array
    {
        if ($user->isTbaiAdmin()) {
            return [
                $this->group('command', '', [
                    $this->link('command', __('entry.nav_command'), route('command.index'), null, ['command.']),
                ]),
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
     *     current: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }>
     */
    private function commandItems(): array
    {
        $items = [
            $this->link('command', __('entry.nav_overview'), route('command.index'), null, ['command.index']),
            $this->parent('registered', __('entry.nav_registered'), [
                $this->child('public', Sector::Public->label(), route('command.sectors.show', ['sector' => Sector::Public->value])),
                $this->child('private', Sector::Private->label(), route('command.sectors.show', ['sector' => Sector::Private->value])),
                $this->child('ppm', Sector::Ppm->label(), route('command.sectors.show', ['sector' => Sector::Ppm->value])),
            ]),
            $this->link('command_map', __('entry.command_map'), route('command.map'), null, ['command.map']),
            $this->link('command_alerts', __('entry.command_alerts'), route('command.alerts'), null, ['command.alerts']),
        ];

        if (config('tbai.features.drivers')) {
            $items[] = $this->link('command_drivers', __('entry.nav_drivers'), route('command.drivers'));
        }

        if (config('tbai.features.incidence')) {
            $items[] = $this->link('command_incidence', __('entry.incidence_title'), route('command.incidence'));
        }

        $items[] = $this->link('daily_returns', __('entry.nav_daily_returns'), route('command.returns'), null, ['command.returns']);

        return $items;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     current: bool,
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
            $this->link('facilities', __('entry.nav_facilities'), route('facilities.index'), null, ['facilities.']),
            $this->link('returns', __('entry.nav_returns'), route('admin.daily-returns.index'), null, ['admin.daily-returns', 'admin.revisions', 'admin.screening-sessions']),
            $this->link('people', __('entry.nav_people'), route('admin.users.index'), $badge, ['admin.users', 'admin.memberships', 'admin.programme-', 'admin.access-requests']),
            $this->link('audit_log', __('entry.nav_audit_log'), route('admin.audit-log.index'), null, ['admin.audit-log']),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     current: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }>
     */
    private function facilityItems(User $user, Team $facilityTeam): array
    {
        $role = $user->teamRole($facilityTeam);
        $items = [
            $this->link('register', __('entry.nav_register_long'), route('returns.today', $facilityTeam), null, ['returns.today', 'returns.update']),
        ];

        if ($role === TeamRole::Owner) {
            $items[] = $this->link('returns_history', __('entry.nav_returns'), route('returns.history', $facilityTeam), null, ['returns.history']);
        }

        if (config('tbai.features.screening')) {
            $items[] = $this->link('screening', __('entry.nav_screening'), route('screening.create', $facilityTeam), null, ['screening.']);
        }

        $items[] = $this->link('my_facility', __('entry.nav_facility'), route('facilities.home', $facilityTeam), null, ['facilities.']);

        return $items;
    }

    /**
     * @param  list<array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     current: bool,
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
     *         current: bool,
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
     * @param  list<string>  $routes  Route name prefixes that mark this link as current.
     * @return array{
     *     key: string,
     *     label: string,
     *     href: string|null,
     *     badge: int|null,
     *     expanded: bool,
     *     current: bool,
     *     children: list<array{key: string, label: string, href: string, badge: int|null}>
     * }
     */
    private function link(string $key, string $label, string $href, ?int $badge = null, array $routes = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'badge' => $badge,
            'expanded' => false,
            'current' => $this->onRoute($routes),
            'children' => [],
        ];
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function onRoute(array $prefixes): bool
    {
        $name = request()->route()?->getName();

        if ($name === null) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{key: string, label: string, href: string, badge: int|null}>  $children
     * @return array{
     *     key: string,
     *     label: string,
     *     href: null,
     *     badge: int|null,
     *     expanded: bool,
     *     current: bool,
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
            'current' => false,
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
     *         current: bool,
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
     *         current: bool,
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

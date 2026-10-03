<?php

namespace App\Support;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;

/**
 * Tab strips for the admin areas that share one sidebar item.
 *
 * People and access is one sidebar item with four tabs. Returns is one item
 * with two. Each tab is a real page, so deep links and the back button work.
 */
class AdminTabs
{
    /**
     * @return list<array{id: string, label: string, href: string, current: bool, badge: int|null}>
     */
    public static function people(string $current): array
    {
        $pending = AccessRequest::query()
            ->where('status', AccessRequestStatus::Pending)
            ->count();

        return self::build($current, [
            ['users', __('entry.nav_users'), route('admin.users.index'), null],
            ['access_requests', __('entry.nav_access_requests'), route('admin.access-requests.index'), $pending > 0 ? $pending : null],
            ['programme_assignments', __('entry.nav_programme_assignments'), route('admin.programme-assignments.index'), null],
            ['programme_access', __('entry.nav_programme_access'), route('admin.programme-access.index'), null],
        ]);
    }

    /**
     * @return list<array{id: string, label: string, href: string, current: bool, badge: int|null}>
     */
    public static function returns(string $current): array
    {
        $tabs = [
            ['daily_returns', __('entry.nav_daily_returns'), route('admin.daily-returns.index'), null],
            ['revisions', __('entry.nav_revisions'), route('admin.revisions.index'), null],
        ];

        if (config('tbai.features.screening')) {
            $tabs[] = ['screening_sessions', __('entry.nav_screening_sessions'), route('admin.screening-sessions.index'), null];
        }

        return self::build($current, $tabs);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: int|null}>  $rows
     * @return list<array{id: string, label: string, href: string, current: bool, badge: int|null}>
     */
    private static function build(string $current, array $rows): array
    {
        return array_map(fn (array $row): array => [
            'id' => $row[0],
            'label' => $row[1],
            'href' => $row[2],
            'current' => $row[0] === $current,
            'badge' => $row[3],
        ], $rows);
    }
}

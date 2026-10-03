<?php

namespace App\Support;

use Illuminate\Http\Request;

class FacilityTabs
{
    public const REGISTER = 'register';

    public const SCREENING = 'screening';

    public const FACILITY = 'facility';

    public const ACCOUNT = 'account';

    /**
     * Which phone tab owns the page being shown.
     *
     * The answer comes from the route name, so a page can never light up a tab
     * because its URL happens to end the same way (for example
     * /settings/profile and /{facility}/profile). Pages that belong to no tab,
     * such as error pages, return null so no tab is marked.
     *
     * Return history and the invitation pages sit under the tab that leads to
     * them, because phone staff have no tab of their own for those yet.
     */
    public function active(Request $request): ?string
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return null;
        }

        return match (true) {
            str_starts_with($name, 'returns.') => self::REGISTER,
            str_starts_with($name, 'screening.') => self::SCREENING,
            str_starts_with($name, 'facilities.') => self::FACILITY,
            str_starts_with($name, 'profile.'),
            str_starts_with($name, 'security.'),
            str_starts_with($name, 'appearance.'),
            str_starts_with($name, 'user-password.'),
            str_starts_with($name, 'teams.') => self::ACCOUNT,
            default => null,
        };
    }
}

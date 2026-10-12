<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Bottom tabs for phone widths, chosen per role.
 *
 * The server owns both the tab list and which tab is current. The current
 * tab comes from the route name, never from a URL suffix, so a page can
 * only light up one tab, and pages that belong to no tab light up none
 * (or More, for the pages that live behind it).
 *
 * @phpstan-type Tab array{id: string, label: string, href: string, icon: string, current: bool}
 * @phpstan-type MoreLink array{id: string, label: string, href: string, icon: string, current: bool}
 */
class FacilityTabs
{
    public const REGISTER = 'register';

    public const RETURNS = 'returns';

    public const SCREENING = 'screening';

    public const SUSPECT_SCREENING = 'suspect_screening';

    public const PATIENTS = 'patients';

    public const FACILITY = 'facility';

    public const ACCOUNT = 'account';

    public const OVERVIEW = 'overview';

    public const REGISTERED = 'registered';

    public const MAP = 'map';

    public const ALERTS = 'alerts';

    public const FACILITIES = 'facilities';

    public const PEOPLE = 'people';

    public const MORE = 'more';

    /**
     * Tabs and More links for this person, or empty lists when the person
     * has no phone tab bar (signed out, or no facility and no programme role).
     *
     * @return array{tabs: list<Tab>, more: list<MoreLink>, active: string|null}
     */
    public function for(?User $user, ?Team $facilityTeam, Request $request): array
    {
        $none = ['tabs' => [], 'more' => [], 'active' => null];

        if ($user === null) {
            return $none;
        }

        $name = $request->route()?->getName();

        if ($user->isTbaiAdmin()) {
            return $this->admin($name);
        }

        if ($user->programmeAssignments()->exists()) {
            return $this->viewer($name);
        }

        if ($facilityTeam !== null && $user->belongsToTeam($facilityTeam)) {
            return $this->facility($user, $facilityTeam, $name);
        }

        return $none;
    }

    /**
     * The tab that owns the current page, for callers that only need the id.
     */
    public function active(Request $request): ?string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $routed = $request->route('current_team');
        $team = $routed instanceof Team ? $routed : $user->currentTeam;

        return $this->for($user, $team, $request)['active'];
    }

    /**
     * @return array{tabs: list<Tab>, more: list<MoreLink>, active: string|null}
     */
    private function facility(User $user, Team $team, ?string $name): array
    {
        $active = match (true) {
            $name === null => null,
            $name === 'returns.history' && Gate::forUser($user)->allows('viewReturns', $team) => self::RETURNS,
            str_starts_with($name, 'returns.') => self::REGISTER,
            str_starts_with($name, 'screening.') => self::SCREENING,
            str_starts_with($name, 'suspect.') => self::SUSPECT_SCREENING,
            str_starts_with($name, 'patients.') => self::PATIENTS,
            str_starts_with($name, 'facilities.') => self::FACILITY,
            $this->isAccountRoute($name) => self::ACCOUNT,
            default => null,
        };

        if (! $user->worksReturnsAt($team)) {
            return $this->frontline($user, $team, $active);
        }

        $tabs = [
            $this->tab(self::REGISTER, __('entry.nav_register'), route('returns.today', $team), $active),
        ];

        if (config('tbai.features.screening')) {
            $tabs[] = $this->tab(self::SCREENING, __('entry.nav_screening'), route('screening.create', $team), $active);
        }

        if (config('tbai.features.suspect_screening')) {
            $tabs[] = $this->tab(self::SUSPECT_SCREENING, __('entry.nav_suspect_screening_short'), route('suspect.index', $team), $active);
        }

        if ($this->showsPatients($user, $team)) {
            $tabs[] = $this->tab(self::PATIENTS, __('entry.nav_patients'), route('patients.index', $team), $active);
        }

        if (Gate::forUser($user)->allows('viewReturns', $team)) {
            $tabs[] = $this->tab(self::RETURNS, __('entry.nav_returns'), route('returns.history', $team), $active);
        }

        $tabs[] = $this->tab(self::FACILITY, __('entry.nav_facility'), route('facilities.home', $team), $active);
        $tabs[] = $this->tab(self::ACCOUNT, __('entry.nav_account'), route('profile.edit'), $active);

        return ['tabs' => $tabs, 'more' => [], 'active' => $active];
    }

    /**
     * Frontline workers (screeners): Suspect screening, Patients, and Account only.
     *
     * @return array{tabs: list<Tab>, more: list<MoreLink>, active: string|null}
     */
    private function frontline(User $user, Team $team, ?string $active): array
    {
        $active = in_array($active, [self::SUSPECT_SCREENING, self::PATIENTS, self::ACCOUNT], true) ? $active : null;
        $tabs = [];

        if (config('tbai.features.suspect_screening') && $user->screensAt($team)) {
            $tabs[] = $this->tab(self::SUSPECT_SCREENING, __('entry.nav_suspect_screening_short'), route('suspect.index', $team), $active);
        }

        if ($this->showsPatients($user, $team)) {
            $tabs[] = $this->tab(self::PATIENTS, __('entry.nav_patients'), route('patients.index', $team), $active);
        }

        $tabs[] = $this->tab(self::ACCOUNT, __('entry.nav_account'), route('profile.edit'), $active);

        return ['tabs' => $tabs, 'more' => [], 'active' => $active];
    }

    /**
     * @return array{tabs: list<Tab>, more: list<MoreLink>, active: string|null}
     */
    private function viewer(?string $name): array
    {
        $active = match (true) {
            $name === null => null,
            $name === 'command.index' => self::OVERVIEW,
            str_starts_with($name, 'command.sectors.'),
            str_starts_with($name, 'command.districts.') => self::REGISTERED,
            $name === 'command.map' => self::MAP,
            str_starts_with($name, 'command.alerts') => self::ALERTS,
            str_starts_with($name, 'command.'),
            $this->isAccountRoute($name) => self::MORE,
            default => null,
        };

        $tabs = [
            $this->tab(self::OVERVIEW, __('entry.nav_overview'), route('command.index'), $active),
            $this->tab(self::REGISTERED, __('entry.nav_registered'), route('command.sectors.show', ['sector' => 'public']), $active),
            $this->tab(self::MAP, __('entry.command_map'), route('command.map'), $active),
            $this->tab(self::ALERTS, __('entry.command_alerts'), route('command.alerts'), $active),
            $this->tab(self::MORE, __('entry.nav_more'), '#more', $active),
        ];

        $more = [
            $this->tab('daily_returns', __('entry.nav_daily_returns'), route('command.returns'), $name !== null && str_starts_with($name, 'command.returns') ? 'daily_returns' : null),
            $this->tab(self::ACCOUNT, __('entry.nav_account'), route('profile.edit'), $name !== null && $this->isAccountRoute($name) ? self::ACCOUNT : null),
        ];

        return ['tabs' => $tabs, 'more' => $more, 'active' => $active];
    }

    /**
     * @return array{tabs: list<Tab>, more: list<MoreLink>, active: string|null}
     */
    private function admin(?string $name): array
    {
        $active = match (true) {
            $name === null => null,
            str_starts_with($name, 'command.') => self::OVERVIEW,
            str_starts_with($name, 'facilities.') => self::FACILITIES,
            str_starts_with($name, 'admin.daily-returns'),
            str_starts_with($name, 'admin.revisions') => self::RETURNS,
            str_starts_with($name, 'admin.users'),
            str_starts_with($name, 'admin.memberships'),
            str_starts_with($name, 'admin.programme-'),
            str_starts_with($name, 'admin.access-requests') => self::PEOPLE,
            str_starts_with($name, 'admin.audit-log'),
            str_starts_with($name, 'admin.suspect-'),
            str_starts_with($name, 'admin.patients'),
            $this->isAccountRoute($name) => self::MORE,
            default => null,
        };

        $tabs = [
            $this->tab(self::OVERVIEW, __('entry.nav_overview'), route('command.index'), $active),
            $this->tab(self::FACILITIES, __('entry.nav_facilities'), route('facilities.index'), $active),
            $this->tab(self::RETURNS, __('entry.nav_returns'), route('admin.daily-returns.index'), $active),
            $this->tab(self::PEOPLE, __('entry.nav_people_short'), route('admin.users.index'), $active),
            $this->tab(self::MORE, __('entry.nav_more'), '#more', $active),
        ];

        $more = [];

        if (config('tbai.features.suspect_screening')) {
            $more[] = $this->tab('suspect_rules', __('entry.nav_suspect_rules'), route('admin.suspect-rules.index'), $name !== null && str_starts_with($name, 'admin.suspect-') ? 'suspect_rules' : null);
        }

        if (config('tbai.features.patients')) {
            $more[] = $this->tab('admin_patients', __('entry.nav_patients'), route('admin.patients.index'), $name !== null && str_starts_with($name, 'admin.patients') ? 'admin_patients' : null);
        }

        $more = [
            ...$more,
            $this->tab('audit_log', __('entry.nav_audit_log'), route('admin.audit-log.index'), $name !== null && str_starts_with($name, 'admin.audit-log') ? 'audit_log' : null),
            $this->tab(self::ACCOUNT, __('entry.nav_account'), route('profile.edit'), $name !== null && $this->isAccountRoute($name) ? self::ACCOUNT : null),
        ];

        return ['tabs' => $tabs, 'more' => $more, 'active' => $active];
    }

    private function showsPatients(User $user, Team $team): bool
    {
        return (bool) config('tbai.features.patients') && $user->screensAt($team);
    }

    /**
     * @return Tab
     */
    private function tab(string $id, string $label, string $href, ?string $active): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'href' => $href,
            'icon' => $id,
            'current' => $active === $id,
        ];
    }

    private function isAccountRoute(string $name): bool
    {
        return str_starts_with($name, 'profile.')
            || str_starts_with($name, 'security.')
            || str_starts_with($name, 'appearance.')
            || str_starts_with($name, 'user-password.')
            || str_starts_with($name, 'teams.');
    }
}

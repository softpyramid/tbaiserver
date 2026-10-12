<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Frontline worker (doctor, LHW, CHW, nurse...). Screens suspects at the
     * facility and does nothing else there: no daily return, no slide
     * photos, no facility profile, no staff management.
     */
    case Screener = 'screener';

    /**
     * Facility name for this stored team role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Facility in-charge',
            self::Admin => 'Senior technician',
            self::Member => 'Technician',
            self::Screener => 'Frontline worker',
        };
    }

    /**
     * Short label used on the facility team roster.
     */
    public function rosterLabel(): string
    {
        return match ($this) {
            self::Owner => 'In-charge',
            self::Admin => 'Senior technician',
            self::Member => 'Technician',
            self::Screener => 'Frontline worker',
        };
    }

    /**
     * Roster label with the frontline designation, for example
     * "Frontline worker (Doctor)". Other roles ignore the designation.
     */
    public function rosterLabelWith(?FrontlineDesignation $designation): string
    {
        if ($this === self::Screener && $designation !== null) {
            return $this->rosterLabel().' ('.$designation->label().')';
        }

        return $this->rosterLabel();
    }

    /**
     * May this role open, save, and backfill the daily BMU return, its
     * history, slide photos, and the facility overview? Frontline workers
     * may not; they only screen.
     */
    public function canWorkReturns(): bool
    {
        return $this !== self::Screener;
    }

    /**
     * May this role start, answer, complete, and read suspect screenings at
     * its facility? Every facility role may.
     *
     * Hook for the later patients feature: screeners will also create and
     * view patients at their facility. Gate that feature on this method (or
     * a sibling canManagePatients()) rather than on canWorkReturns().
     */
    public function canScreen(): bool
    {
        return true;
    }

    /**
     * Only frontline workers carry a designation.
     */
    public function takesDesignation(): bool
    {
        return $this === self::Screener;
    }

    /**
     * Roles that can be chosen when inviting or changing a member.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function rosterOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->rosterLabel()])
            ->values()
            ->all();
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                TeamPermission::UpdateTeam,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
            ],
            self::Member => [],
            self::Screener => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
            self::Screener => 0,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned on the generic team settings page
     * (excludes Owner, and Screener, which needs a designation and only
     * exists on facility teams).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner && $role !== self::Screener)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}

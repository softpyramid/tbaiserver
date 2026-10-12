<?php

namespace App\Support;

use App\Enums\FrontlineDesignation;
use App\Enums\TeamRole;
use Illuminate\Validation\ValidationException;

/**
 * One facility role choice: a team role plus, for a frontline worker, a
 * designation. Role pickers that send a single value (the roster "Change
 * role" list, the admin People select) use "screener:doctor" style values;
 * forms with their own designation field send role and designation apart.
 */
class MemberRoleChoice
{
    public function __construct(
        public readonly TeamRole $role,
        public readonly ?FrontlineDesignation $designation,
    ) {}

    public function value(): string
    {
        return self::valueFor($this->role, $this->designation);
    }

    public static function valueFor(TeamRole $role, ?FrontlineDesignation $designation): string
    {
        return $role->takesDesignation() && $designation !== null
            ? $role->value.':'.$designation->value
            : $role->value;
    }

    /**
     * Single-value options for a facility member, one per designation for
     * frontline workers.
     *
     * @param  list<TeamRole>  $roles
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $roles, bool $rosterLabels = true): array
    {
        $options = [];

        foreach ($roles as $role) {
            if (! $role->takesDesignation()) {
                $options[] = ['value' => $role->value, 'label' => $rosterLabels ? $role->rosterLabel() : $role->label()];

                continue;
            }

            foreach (FrontlineDesignation::cases() as $designation) {
                $options[] = [
                    'value' => self::valueFor($role, $designation),
                    'label' => $role->rosterLabelWith($designation),
                ];
            }
        }

        return $options;
    }

    /**
     * Roles an in-charge may give a facility member (never a second in-charge).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function rosterOptions(): array
    {
        return self::options([TeamRole::Admin, TeamRole::Member, TeamRole::Screener]);
    }

    /**
     * Read a role and designation from request input. A designation is
     * required for a frontline worker and refused for every other role.
     *
     * @param  list<TeamRole>  $allowed
     *
     * @throws ValidationException
     */
    public static function fromInput(mixed $role, mixed $designation, array $allowed): self
    {
        $role = is_string($role) ? trim($role) : '';
        $designation = is_string($designation) ? trim($designation) : '';

        if (str_contains($role, ':')) {
            [$role, $embedded] = explode(':', $role, 2);

            if ($designation !== '' && $designation !== $embedded) {
                throw ValidationException::withMessages(['designation' => __('entry.designation_mismatch')]);
            }

            $designation = $embedded;
        }

        $teamRole = TeamRole::tryFrom($role);

        if (! $teamRole instanceof TeamRole || ! in_array($teamRole, $allowed, true)) {
            throw ValidationException::withMessages(['role' => __('validation.in', ['attribute' => 'role'])]);
        }

        if (! $teamRole->takesDesignation()) {
            if ($designation !== '') {
                throw ValidationException::withMessages(['designation' => __('entry.designation_only_frontline')]);
            }

            return new self($teamRole, null);
        }

        $frontline = FrontlineDesignation::tryFrom($designation);

        if (! $frontline instanceof FrontlineDesignation) {
            throw ValidationException::withMessages(['designation' => __('entry.designation_required')]);
        }

        return new self($teamRole, $frontline);
    }
}

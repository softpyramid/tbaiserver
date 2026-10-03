<?php

namespace App\Support;

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;

class ProgrammeAssignmentValidator
{
    /**
     * Field errors when the scope does not match the role, or the scope row is missing.
     *
     * @return array<string, string>
     */
    public function errors(ProgrammeRole $role, ?string $scopeType, mixed $scopeId): array
    {
        $expected = $role->scopeType();
        $scopeIsEmpty = $scopeId === null || $scopeId === '';

        if ($expected === null) {
            if ($scopeType !== null || ! $scopeIsEmpty) {
                return [
                    'scope_type' => 'TB-AI admin assignments do not take a geographic scope.',
                ];
            }

            return [];
        }

        if ($scopeType !== $expected->value) {
            return [
                'scope_type' => 'The scope type does not match the role.',
            ];
        }

        if ($scopeIsEmpty || ! is_numeric($scopeId)) {
            return [
                'scope_id' => 'A scope is required for this role.',
            ];
        }

        $id = (int) $scopeId;
        $exists = match ($expected) {
            ScopeType::Country => Country::query()->whereKey($id)->exists(),
            ScopeType::Province => Province::query()->whereKey($id)->exists(),
            ScopeType::City => City::query()->whereKey($id)->exists(),
        };

        if (! $exists) {
            return [
                'scope_id' => 'That scope does not exist.',
            ];
        }

        return [];
    }
}

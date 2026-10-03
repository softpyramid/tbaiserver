<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\City;
use App\Models\ProgrammeAssignment;
use App\Models\User;

test('a programme assignment scope must match the role', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    $officer = User::factory()->create();
    $city = City::factory()->create();

    $this->actingAs($admin)
        ->post(route('programme-assignments.store'), [
            'user_id' => $officer->id,
            'role' => ProgrammeRole::CityOfficer->value,
            'scope_type' => ScopeType::Province->value,
            'scope_id' => $city->province_id,
        ])
        ->assertSessionHasErrors('scope_type');

    $this->actingAs($admin)
        ->post(route('programme-assignments.store'), [
            'user_id' => $officer->id,
            'role' => ProgrammeRole::CityOfficer->value,
            'scope_type' => ScopeType::City->value,
            'scope_id' => $city->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('programme_assignments', [
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer->value,
        'scope_type' => ScopeType::City->value,
        'scope_id' => $city->id,
    ]);
});

test('a tb-ai admin assignment does not take a geographic scope', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
    ]);
    $city = City::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('programme-assignments.store'), [
            'user_id' => $other->id,
            'role' => ProgrammeRole::TbaiAdmin->value,
            'scope_type' => ScopeType::City->value,
            'scope_id' => $city->id,
        ])
        ->assertSessionHasErrors('scope_type');

    $this->actingAs($admin)
        ->post(route('programme-assignments.store'), [
            'user_id' => $other->id,
            'role' => ProgrammeRole::TbaiAdmin->value,
        ])
        ->assertRedirect();

    expect(ProgrammeAssignment::query()->where('user_id', $other->id)->count())->toBe(1);
});

test('members cannot assign programme roles', function () {
    $member = User::factory()->create();
    $city = City::factory()->create();

    $this->actingAs($member)
        ->post(route('programme-assignments.store'), [
            'user_id' => $member->id,
            'role' => ProgrammeRole::CityOfficer->value,
            'scope_type' => ScopeType::City->value,
            'scope_id' => $city->id,
        ])
        ->assertForbidden();
});

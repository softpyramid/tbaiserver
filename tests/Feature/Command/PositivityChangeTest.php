<?php

use App\Enums\ProgrammeRole;
use App\Enums\Sector;
use App\Models\City;
use App\Models\User;
use App\Support\CommandApi\PositivityChange;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('positivity change is a signed difference in percentage points', function () {
    expect(PositivityChange::label(200, 1000, 150, 1000))->toBe('+5.0 pp')
        ->and(PositivityChange::label(100, 1000, 200, 1000))->toBe('-10.0 pp')
        ->and(PositivityChange::label(1000, 1000, 1000, 1000))->toBe('+0.0 pp');
});

test('positivity change says no comparison when either period has no tests', function () {
    expect(PositivityChange::label(0, 0, 150, 1000))->toBe('No comparison')
        ->and(PositivityChange::label(200, 1000, 0, 0))->toBe('No comparison')
        ->and(PositivityChange::label(5, 0, 1, 0))->toBe('No comparison');
});

test('the positivity kpi shows the change in percentage points against the previous period', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-28 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $facility = commandFacility(City::factory()->create(['name' => 'Karachi South']), Sector::Public, 'South BMU');

    commandReturn($facility, '2026-02-05', suspects: 40, tested: 40, positive: 10, negative: 30, started: 10, followUp: 0);
    commandReturn($facility, '2026-01-15', suspects: 50, tested: 50, positive: 5, negative: 45, started: 5, followUp: 0);

    $this->actingAs($admin)
        ->get(route('command.index', ['from' => '2026-02-01', 'to' => '2026-02-28']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/index')
            ->where('kpis.4.value', '25%')
            ->where('kpis.4.change', '+15.0 pp'));
});

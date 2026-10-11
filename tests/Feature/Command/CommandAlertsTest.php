<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\AlertRule;
use App\Models\City;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Database\Seeders\AlertRuleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('the alert rule seeder stores four demo rules and skips production', function () {
    $this->app['env'] = 'local';
    $this->seed(AlertRuleSeeder::class);

    expect(AlertRule::query()->orderBy('id')->pluck('label')->all())->toBe([
        'Positives with no treatment start',
        'Facility turnaround up 2.3x',
        'Positivity rising 3 periods in a row',
        'Turnaround improvement',
    ])->and(AlertRule::query()->count())->toBe(4);

    AlertRule::query()->delete();
    $this->app['env'] = 'production';
    (new AlertRuleSeeder)->run();

    expect(AlertRule::query()->count())->toBe(0);

    $this->app['env'] = 'testing';
    (new AlertRuleSeeder)->run();

    expect(AlertRule::query()->count())->toBe(0);

    $this->app['env'] = 'staging';
    (new AlertRuleSeeder)->run();

    expect(AlertRule::query()->count())->toBe(4);
});

test('alerts link to a district or facility and skip rules the returns cannot answer', function () {
    $this->app['env'] = 'local';
    $this->seed(AlertRuleSeeder::class);
    $this->app['env'] = 'testing';

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $sukkur = City::factory()->create(['name' => 'Sukkur']);
    $west = City::factory()->create(['name' => 'Karachi West']);
    $larkana = City::factory()->create(['name' => 'Larkana']);
    $hyderabad = City::factory()->create(['name' => 'Hyderabad']);
    $badin = City::factory()->create(['name' => 'Badin']);

    commandReturn(commandFacility($sukkur, Sector::Public, 'Sukkur BMU'), '2026-02-10', suspects: 6, tested: 4, positive: 4, negative: 0, started: 1, followUp: 0);
    $westFacility = commandFacility($west, Sector::Public, 'West BMU');
    commandReturn($westFacility, '2026-01-15', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 0, tat: 2);
    commandReturn($westFacility, '2026-02-10', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 0, tat: 5);
    commandReturn(commandFacility($west, Sector::Public, 'Quiet BMU'), '2026-02-11', suspects: 3, tested: 3, positive: 0, negative: 3, started: 0, followUp: 0, tat: 9);
    commandReturn(commandFacility($larkana, Sector::Public, 'Larkana BMU'), '2026-01-15', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 0, tat: 4);
    commandReturn(commandFacility($larkana, Sector::Public, 'Larkana BMU 2'), '2026-02-10', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 0, tat: 2);

    $hyderabadFacility = commandFacility($hyderabad, Sector::Public, 'Hyderabad BMU');
    commandReturn($hyderabadFacility, '2025-12-15', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 0);
    commandReturn($hyderabadFacility, '2026-01-15', suspects: 10, tested: 10, positive: 2, negative: 8, started: 2, followUp: 0);
    commandReturn($hyderabadFacility, '2026-02-15', suspects: 10, tested: 10, positive: 3, negative: 7, started: 3, followUp: 0);
    commandReturn($hyderabadFacility, '2026-03-15', suspects: 10, tested: 10, positive: 4, negative: 6, started: 4, followUp: 0);

    $badinFacility = commandFacility($badin, Sector::Public, 'Badin BMU');
    commandReturn($badinFacility, '2025-12-15', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 0);
    commandReturn($badinFacility, '2026-01-15', suspects: 4, tested: 0, positive: 0, negative: 0, started: 0, followUp: 0);
    commandReturn($badinFacility, '2026-02-15', suspects: 10, tested: 10, positive: 3, negative: 7, started: 3, followUp: 0);
    commandReturn($badinFacility, '2026-03-15', suspects: 10, tested: 10, positive: 4, negative: 6, started: 4, followUp: 0);

    $this->actingAs($admin)
        ->get(route('command.alerts', ['from' => '2026-02-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($sukkur, $westFacility, $larkana, $hyderabad) {
            $page->component('command/alerts')
                ->where('badge', 'Thresholds pending the TB programme')
                ->where('canEdit', true)
                ->where('title', 'Alerts');

            $alerts = collect($page->toArray()['props']['alerts']);
            $encoded = json_encode($alerts);

            $gap = $alerts->firstWhere('label', 'Positives with no treatment start');
            $turnaround = $alerts->firstWhere('label', 'Facility turnaround up 2.3x');
            $streak = $alerts->firstWhere('label', 'Positivity rising 3 periods in a row');
            $improved = $alerts->firstWhere('label', 'Turnaround improvement');

            expect($gap['place']['type'])->toBe('district')
                ->and($gap['place']['name'])->toBe('Sukkur')
                ->and($gap['href'])->toContain((string) $sukkur->id)
                ->and($turnaround['place']['type'])->toBe('facility')
                ->and($turnaround['place']['name'])->toBe('West BMU')
                ->and($turnaround['href'])->toContain((string) $westFacility->id)
                ->and($streak['place']['type'])->toBe('district')
                ->and($streak['place']['name'])->toBe('Hyderabad')
                ->and($streak['href'])->toContain((string) $hyderabad->id)
                ->and($improved['place']['type'])->toBe('district')
                ->and($improved['place']['name'])->toBe('Larkana')
                ->and($improved['href'])->toContain((string) $larkana->id)
                ->and($encoded)->not->toContain('Quiet BMU')
                ->and($encoded)->not->toContain('Badin')
                ->and($encoded)->not->toContain('national_id')
                ->and($encoded)->not->toContain('patient');

            $rules = collect($page->toArray()['props']['rules'])->keyBy('label');

            expect($rules['Positivity rising 3 periods in a row']['status'])->toBe('triggered')
                ->and($rules['Positives with no treatment start']['status'])->toBe('triggered');
        });
});

test('an admin can edit an alert rule and another command role cannot', function () {
    $this->app['env'] = 'local';
    $this->seed(AlertRuleSeeder::class);
    $this->app['env'] = 'testing';

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create();
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);
    $rule = AlertRule::query()->where('key', 'positives_without_treatment')->firstOrFail();

    $payload = [
        'metric' => 'treatment_gap',
        'comparator' => 'gt',
        'threshold' => 9,
        'severity' => 'critical',
        'label' => 'Gap revised',
    ];

    $this->actingAs($officer)
        ->patch(route('command.alerts.update', $rule), $payload)
        ->assertForbidden();

    expect($rule->fresh()->label)->toBe('Positives with no treatment start');

    $this->actingAs($officer)
        ->get(route('command.alerts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('badge', 'Thresholds pending the TB programme')
            ->where('rules.0.updateUrl', null));

    $this->actingAs($admin)
        ->patch(route('command.alerts.update', $rule), $payload)
        ->assertRedirect();

    expect($rule->fresh()->label)->toBe('Gap revised')
        ->and((float) $rule->fresh()->threshold)->toBe(9.0);

    $facility = Facility::factory()->create();
    $member = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);

    $this->actingAs($member)->get(route('command.alerts'))->assertForbidden();
    $this->actingAs($member)
        ->patch(route('command.alerts.update', $rule), $payload)
        ->assertForbidden();
});

test('the alerts page sends the severity labels and keeps the stored severities and the thresholds notice', function () {
    $this->app['env'] = 'local';
    $this->seed(AlertRuleSeeder::class);
    $this->app['env'] = 'testing';

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.alerts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/alerts')
            ->where('badge', 'Thresholds pending the TB programme')
            ->where('alertLabels.severityAll', 'All')
            ->where('alertLabels.severityFilter', 'Filter alerts by severity')
            ->where('rules', fn ($rules) => collect($rules)->pluck('severity')->all() === AlertRule::query()->orderBy('id')->pluck('severity')->all()));
});

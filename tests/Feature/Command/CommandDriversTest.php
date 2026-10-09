<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => enableFeature('drivers', 'incidence'));

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('priority districts are the top 3 from daily returns and pending drivers have no numbers', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $alpha = City::factory()->create(['name' => 'Alpha']);
    $beta = City::factory()->create(['name' => 'Beta']);
    $cairo = City::factory()->create(['name' => 'Cairo']);
    $delta = City::factory()->create(['name' => 'Delta']);

    commandReturn(commandFacility($alpha, Sector::Public, 'Alpha BMU'), '2026-06-01', suspects: 10, tested: 10, positive: 1, negative: 9, started: 1, followUp: 86421, tat: 1);
    commandReturn(commandFacility($beta, Sector::Public, 'Beta BMU'), '2026-06-01', suspects: 30, tested: 10, positive: 8, negative: 2, started: 1, followUp: 0, tat: 5);
    commandReturn(commandFacility($cairo, Sector::Public, 'Cairo BMU'), '2026-06-01', suspects: 20, tested: 20, positive: 2, negative: 18, started: 0, followUp: 0, tat: 3);
    commandReturn(commandFacility($delta, Sector::Public, 'Delta BMU'), '2026-06-01', suspects: 5, tested: 0, positive: 0, negative: 0, started: 0, followUp: 0, tat: null);

    $this->actingAs($admin)
        ->get(route('command.drivers'))
        ->assertOk()
        ->assertDontSee('86421')
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/drivers')
            ->where('title', 'TB Drivers and Intervention Intelligence')
            ->where('badge', 'Pending the TB programme')
            ->where('categories.0.formula', 'Top 3 districts with a provisional median TAT, highest first. TAT is the average of reported medians, weighted by tested count.')
            ->where('categories.0.status', 'available')
            ->where('categories.0.actions', [
                'Increase testing capacity.',
                'Reduce specimen transport delays.',
            ])
            ->has('categories.0.districts', 3)
            ->where('categories.0.districts.0.name', 'Beta')
            ->where('categories.0.districts.0.value', '5')
            ->where('categories.0.districts.1.name', 'Cairo')
            ->where('categories.0.districts.1.value', '3')
            ->where('categories.0.districts.2.name', 'Alpha')
            ->where('categories.0.districts.2.value', '1')
            ->missing('categories.0.score')
            ->missing('categories.0.districts.0.score')
            ->where('categories.1.formula', 'Top 3 districts with tested above zero, highest positivity first. Positivity is positive results divided by valid completed tests, times 100.')
            ->where('categories.1.actions', [
                'Targeted screening.',
                'Intensify contact investigation.',
            ])
            ->where('categories.1.districts.0.name', 'Beta')
            ->where('categories.1.districts.0.value', '80%')
            ->where('categories.1.districts.1.name', 'Alpha')
            ->where('categories.1.districts.1.value', '10%')
            ->where('categories.1.districts.2.name', 'Cairo')
            ->where('categories.1.districts.2.value', '10%')
            ->where('categories.2.formula', 'Top 3 districts by treatment gap, highest first. Treatment gap is positive minus started treatment.')
            ->where('categories.2.actions', [
                'Investigate the treatment-initiation gap.',
                'Link every diagnosis to a treatment start.',
            ])
            ->where('categories.2.districts.0.name', 'Beta')
            ->where('categories.2.districts.0.value', '7')
            ->where('categories.2.districts.1.name', 'Cairo')
            ->where('categories.2.districts.1.value', '2')
            ->where('categories.2.districts.2.name', 'Alpha')
            ->where('categories.2.districts.2.value', '0')
            ->where('categories.3.status', 'unavailable')
            ->where('categories.3.actions', [
                'Trace interrupted patients.',
                'Strengthen adherence support.',
            ])
            ->has('categories.3.districts', 0)
            ->missing('categories.3.value')
            ->where('categories.4.formula', 'Top 3 districts with suspects above zero, lowest testing coverage first. Testing coverage is tested divided by suspects.')
            ->where('categories.4.actions', [
                'Improve referral completion.',
                'Expand diagnostic access.',
            ])
            ->where('categories.4.districts.0.name', 'Delta')
            ->where('categories.4.districts.0.value', '0%')
            ->where('categories.4.districts.1.name', 'Beta')
            ->where('categories.4.districts.1.value', '33.3%')
            ->where('categories.4.districts.2.name', 'Alpha')
            ->where('categories.4.districts.2.value', '100%')
            ->where('categories.5.formula', 'Top 3 districts by suspects, highest first.')
            ->where('categories.5.actions', [
                'Prioritise high-burden BMUs.',
                'Allocate GeneXpert and staff.',
            ])
            ->where('categories.5.districts.0.name', 'Beta')
            ->where('categories.5.districts.0.value', '30')
            ->where('categories.5.districts.1.name', 'Cairo')
            ->where('categories.5.districts.1.value', '20')
            ->where('categories.5.districts.2.name', 'Alpha')
            ->where('categories.5.districts.2.value', '10')
            ->where('pending', [
                ['key' => 'hiv_testing', 'label' => 'HIV testing', 'status' => 'Data source pending'],
                ['key' => 'contact_evaluation', 'label' => 'contact evaluation', 'status' => 'Data source pending'],
                ['key' => 'malnutrition', 'label' => 'malnutrition', 'status' => 'Data source pending'],
                ['key' => 'distance', 'label' => 'distance', 'status' => 'Data source pending'],
                ['key' => 'socioeconomic', 'label' => 'socioeconomic', 'status' => 'Data source pending'],
            ])
            ->missing('pending.0.value')
            ->missing('pending.1.value')
            ->missing('pending.2.value')
            ->missing('pending.3.value')
            ->missing('pending.4.value'));
});

test('a driver is unavailable when its signal cannot be calculated', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Quiet']);
    commandReturn(commandFacility($city, Sector::Public, 'Quiet BMU'), '2026-06-01', suspects: 0, tested: 0, positive: 0, negative: 0, started: 0, followUp: 86421, tat: null);

    $this->actingAs($admin)
        ->get(route('command.drivers'))
        ->assertOk()
        ->assertDontSee('86421')
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.status', 'unavailable')
            ->has('categories.0.districts', 0)
            ->where('categories.1.status', 'unavailable')
            ->has('categories.1.districts', 0)
            ->where('categories.2.status', 'available')
            ->where('categories.2.districts.0.value', '0')
            ->where('categories.3.status', 'unavailable')
            ->has('categories.3.districts', 0)
            ->where('categories.4.status', 'unavailable')
            ->has('categories.4.districts', 0)
            ->where('categories.5.status', 'available')
            ->where('categories.5.districts.0.value', '0')
            ->where('badge', 'Pending the TB programme'));
});

test('no returns leaves every signal category unavailable', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.drivers'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $categories = $page->toArray()['props']['categories'];

            expect($categories)->toHaveCount(6);

            foreach ($categories as $category) {
                expect($category['status'])->toBe('unavailable')
                    ->and($category['districts'])->toBe([])
                    ->and($category)->not->toHaveKey('score')
                    ->and($category)->not->toHaveKey('value');
            }

            $page->where('pending.0.status', 'Data source pending')
                ->missing('pending.0.value');
        });
});

test('a city officer does not see another city and facility staff cannot open drivers', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00', 'Asia/Karachi'));

    $city = City::factory()->create(['name' => 'Karachi West']);
    $otherCity = City::factory()->create(['name' => 'Thatta']);
    commandReturn(commandFacility($city, Sector::Public, 'West BMU'), '2026-02-01', suspects: 7, tested: 4, positive: 1, negative: 3, started: 1, followUp: 0, tat: 2);
    commandReturn(commandFacility($otherCity, Sector::Private, 'Thatta BMU'), '2026-02-01', suspects: 90, tested: 40, positive: 20, negative: 20, started: 10, followUp: 0, tat: 9);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.drivers', [
            'district' => $otherCity->id,
            'province' => $otherCity->province_id,
        ]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $encoded = json_encode($page->toArray()['props']['categories']);

            expect($encoded)->not->toContain('Thatta')
                ->and($encoded)->not->toContain('90');

            $page->where('categories.5.districts.0.name', 'Karachi West')
                ->where('categories.5.districts.0.value', '7')
                ->where('filters.district', '')
                ->where('filters.province', '');

            $labels = collect($page->toArray()['props']['nav']['groups'])
                ->flatMap(fn (array $group): array => array_column($group['items'], 'label'))
                ->all();
            $screenLabels = array_column($page->toArray()['props']['screens'], 'label');

            expect($labels)->toContain('Drivers and Interventions')
                ->and($labels)->toContain('Incidence')
                ->and($labels)->not->toContain('AI Assisted Testing')
                ->and($screenLabels)->not->toContain('AI Assisted Testing');
        });

    $facility = $city->facilities()->first();
    $member = User::factory()->create();
    $owner = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);
    joinFacility($owner, $facility, TeamRole::Owner);

    $this->actingAs($member)->get(route('command.drivers'))->assertForbidden();
    $this->actingAs($owner)->get(route('command.drivers'))->assertForbidden();
    $this->actingAs($member)->get(route('command.incidence'))->assertForbidden();

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $keys = collect($page->toArray()['props']['nav']['groups'])
                ->flatMap(fn (array $group): array => array_column($group['items'], 'key'))
                ->all();
            $labels = collect($page->toArray()['props']['nav']['groups'])
                ->flatMap(fn (array $group): array => array_column($group['items'], 'label'))
                ->all();

            expect($keys)->not->toContain('command_drivers')
                ->and($keys)->not->toContain('command_incidence')
                ->and($labels)->not->toContain('AI Assisted Testing');
        });
});

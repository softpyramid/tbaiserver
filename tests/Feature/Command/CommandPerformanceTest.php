<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Models\CaseNotification;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\IncidenceEstimate;
use App\Models\PopulationDenominator;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\CommandCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('command lookup indexes exist', function () {
    $daily = collect(Schema::getIndexes('daily_returns'))->pluck('name');
    $facilities = collect(Schema::getIndexes('facilities'))->pluck('name');

    expect($daily)->toContain('daily_returns_reported_on_facility_id_index')
        ->and($facilities)->toContain('facilities_city_sector_status_index');
});

test('an open period uses the short cache ttl and a closed period the long one', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Karachi'));

    $open = CommandCache::ttl(['from' => '2026-01-01', 'to' => '2026-06-20']);
    $closed = CommandCache::ttl(['from' => '2025-01-01', 'to' => '2025-12-31']);
    $openSeconds = $open->getTimestamp() - now()->getTimestamp();
    $closedSeconds = $closed->getTimestamp() - now()->getTimestamp();

    expect($openSeconds)->toBeGreaterThan(4 * 60)
        ->and($openSeconds)->toBeLessThanOrEqual(5 * 60)
        ->and($closedSeconds)->toBeGreaterThan(23 * 3600)
        ->and($closedSeconds)->toBeLessThanOrEqual(24 * 3600);
});

test('admin, a provincial viewer and a district officer get different cache keys', function () {
    $sindh = Province::factory()->create();
    $west = City::factory()->create(['province_id' => $sindh->id]);
    $admin = User::factory()->create();
    $viewer = User::factory()->create();
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $sindh->id,
    ]);
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $west->id,
    ]);

    $selection = ['from' => '2026-01-01', 'to' => '2026-06-20'];
    $adminKey = CommandCache::key('cumulative', $admin, $selection);
    $viewerKey = CommandCache::key('cumulative', $viewer, $selection);
    $officerKey = CommandCache::key('cumulative', $officer, $selection);

    expect($adminKey)->not->toBe($viewerKey)
        ->and($viewerKey)->not->toBe($officerKey)
        ->and($adminKey)->not->toBe($officerKey);

    $stored = CommandCache::remember('cumulative', $admin, $selection, fn (): array => ['scope' => 'admin']);
    $served = CommandCache::remember('cumulative', $officer, $selection, fn (): array => ['scope' => 'officer']);
    $store = CommandCache::supportsTags() ? Cache::tags([CommandCache::TAG]) : Cache::store();

    expect($stored)->toBe(['scope' => 'admin'])
        ->and($served)->toBe(['scope' => 'officer'])
        ->and($store->get($adminKey))->toBe(['scope' => 'admin'])
        ->and($store->get($officerKey))->toBe(['scope' => 'officer']);
});

test('saving or deleting a daily return changes the next command response', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);
    $facility = Facility::factory()->create();
    $return = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'reported_on' => '2026-03-01',
        'suspects' => 4,
        'tested' => 4,
        'positive' => 1,
        'negative' => 3,
        'started_treatment' => 1,
    ]);

    $this->actingAs($admin)->getJson(route('api.command.cumulative'))->assertOk();
    $return->update(['suspects' => 40]);

    expect($this->actingAs($admin)->getJson(route('api.command.cumulative'))->json('data.cascade.0.value'))->toBe(40);

    $return->delete();

    expect($this->actingAs($admin)->getJson(route('api.command.cumulative'))->json('data.cascade.0.value'))->toBe(0);
});

test('importing case notifications or editing population and incidence data busts the cache', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);
    $facility = Facility::factory()->create(['code' => 'BMU-PERF']);
    $before = CommandCache::version();

    $this->actingAs($admin)->post(route('admin.case-notifications.import'), [
        'file' => UploadedFile::fake()->createWithContent(
            'rows.csv',
            "facility_code,month,new_cases,relapse_cases,other_previously_treated,history_unknown,source\nBMU-PERF,2026-03,4,1,0,0,NTMIS\n",
        ),
    ])->assertRedirect();

    expect(CommandCache::version())->toBeGreaterThan($before)
        ->and(CaseNotification::query()->where('facility_id', $facility->id)->count())->toBe(1);

    $populationVersion = CommandCache::version();
    PopulationDenominator::factory()->create(['population' => 1000]);

    expect(CommandCache::version())->toBeGreaterThan($populationVersion);

    $estimateVersion = CommandCache::version();
    IncidenceEstimate::factory()->create(['rate_per_100k' => 10]);

    expect(CommandCache::version())->toBeGreaterThan($estimateVersion);
});

test('from, province, district and sector each change the totals and a mismatch is reported', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Karachi'));

    $sindh = Province::factory()->create(['name' => 'Sindh']);
    $punjab = Province::factory()->create(['name' => 'Punjab']);
    $west = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi West']);
    $hyderabad = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Hyderabad']);
    $lahore = City::factory()->create(['province_id' => $punjab->id, 'name' => 'Lahore']);
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    $westPublic = Facility::factory()->create(['city_id' => $west->id, 'sector' => Sector::Public]);
    $westPrivate = Facility::factory()->create(['city_id' => $west->id, 'sector' => Sector::Private]);
    $hyderabadPublic = Facility::factory()->create(['city_id' => $hyderabad->id, 'sector' => Sector::Public]);
    $lahorePublic = Facility::factory()->create(['city_id' => $lahore->id, 'sector' => Sector::Public]);

    DailyReturn::factory()->create(['facility_id' => $westPublic->id, 'reported_on' => '2026-01-15', 'suspects' => 10, 'tested' => 8, 'positive' => 2, 'negative' => 6, 'started_treatment' => 1]);
    DailyReturn::factory()->create(['facility_id' => $westPrivate->id, 'reported_on' => '2026-02-15', 'suspects' => 4, 'tested' => 4, 'positive' => 1, 'negative' => 3, 'started_treatment' => 1]);
    DailyReturn::factory()->create(['facility_id' => $hyderabadPublic->id, 'reported_on' => '2026-03-15', 'suspects' => 7, 'tested' => 7, 'positive' => 1, 'negative' => 6, 'started_treatment' => 1]);
    DailyReturn::factory()->create(['facility_id' => $lahorePublic->id, 'reported_on' => '2026-04-15', 'suspects' => 20, 'tested' => 10, 'positive' => 2, 'negative' => 8, 'started_treatment' => 2]);

    $all = $this->actingAs($admin)->get(route('command.index'))->assertOk();

    $all->assertInertia(fn (Assert $page) => $page
        ->where('totals.suspects', 41)
        ->where('filters.active.from', false)
        ->where('filters.active.to', false)
        ->where('filters.active.province', false)
        ->where('filters.active.district', false)
        ->where('filters.active.sector', false)
        ->where('filters.notice', null)
        ->where('filters.defaults.from', '2026-01-01')
        ->where('filters.defaults.to', '2026-06-20')
        ->has('filters.presets', 3)
        ->where('options.districts', function (mixed $districts) use ($west): bool {
            $match = collect($districts)->firstWhere('value', (string) $west->id);

            return is_array($match) && ($match['province_id'] ?? null) === (string) $west->province_id;
        }));

    $this->actingAs($admin)
        ->get(route('command.index', ['from' => '2026-04-01', 'to' => '2026-04-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.suspects', 20)
            ->where('filters.active.from', true));

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.suspects', 21)
            ->where('filters.active.province', true));

    $this->actingAs($admin)
        ->get(route('command.index', ['district' => $west->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.suspects', 14)
            ->where('filters.active.district', true));

    $this->actingAs($admin)
        ->get(route('command.index', ['sector' => 'private']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.suspects', 4)
            ->where('filters.active.sector', true));

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id, 'district' => $lahore->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.notice', 'That district is not in the selected province, so it was left out.')
            ->where('filters.district', '')
            ->where('totals.suspects', 21));

    $this->actingAs($admin)
        ->get(route('command.index', ['from' => '2026-01-01', 'to' => '2026-06-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.active.from', false)
            ->where('filters.active.to', false)
            ->where('totals.suspects', 41));
});

test('the overview defers notification, estimate and months', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    $page = $this->actingAs($admin)->get(route('command.index'))->assertOk()->viewData('page');

    expect($page['deferredProps']['default'] ?? [])->toEqualCanonicalizing(['months', 'notification', 'estimate']);
});

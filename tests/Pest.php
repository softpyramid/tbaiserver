<?php

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\SuspectRuleSet;
use App\Models\User;
use Database\Seeders\SuspectRuleSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Turn product areas on for a test. They are off by default, as in production.
 */
function enableFeature(string ...$features): void
{
    foreach ($features as $feature) {
        config(["tbai.features.{$feature}" => true]);
    }
}

/**
 * A facility with one staff member at the given role, for suspect screening tests.
 *
 * @return array{0: User, 1: Facility}
 */
function suspectStaff(TeamRole $role = TeamRole::Member, ?Facility $facility = null): array
{
    $user = User::factory()->create();
    $facility ??= Facility::factory()->create();
    $facility->team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($facility->team);

    return [$user, $facility];
}

function suspectAdmin(): User
{
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    return $admin->fresh() ?? $admin;
}

/**
 * The seeded 2026.10-draft.1 rule set with every relation loaded. Seeding also
 * adds draft.2, which the resolver serves as the newest draft.
 */
function suspectDraftRuleSet(): SuspectRuleSet
{
    (new SuspectRuleSetSeeder)->run();

    return SuspectRuleSet::query()
        ->where('version', SuspectRuleSetSeeder::VERSION)
        ->firstOrFail()
        ->load(['questions', 'scoreItems', 'clinicalRules', 'riskPatterns']);
}

/**
 * The seeded 2026.10-draft.2 rule set (thresholds 70/40/20/70) with every relation loaded.
 */
function suspectDraftTwoRuleSet(): SuspectRuleSet
{
    (new SuspectRuleSetSeeder)->run();

    return SuspectRuleSet::query()
        ->where('version', SuspectRuleSetSeeder::VERSION_2)
        ->firstOrFail()
        ->load(['questions', 'scoreItems', 'clinicalRules', 'riskPatterns']);
}

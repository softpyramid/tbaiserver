<?php

use App\Models\User;
use App\Support\RoleHome;
use Database\Seeders\LoginSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * The six demo roles and where each one should land. The TB-AI admin opens
 * the facilities list; programme viewers open the command center; facility
 * staff open today's register for their BMU.
 *
 * @return array<string, string>
 */
function demoLandings(): array
{
    $register = route('returns.today', 'karachi-west-bmu');

    return [
        'admin@tbai.test' => route('facilities.index'),
        'country@tbai.test' => route('command.index'),
        'sindh@tbai.test' => route('command.index'),
        'karachi-west@tbai.test' => route('command.index'),
        'incharge@tbai.test' => $register,
        'member@tbai.test' => $register,
    ];
}

beforeEach(function () {
    $this->app['env'] = 'local';
    config(['access.seed_login_password' => 'server-secret']);
    $this->seed(LoginSeeder::class);
    $this->app['env'] = 'testing';
});

test('each demo role lands on its own home after signing in', function () {
    foreach (demoLandings() as $email => $landing) {
        Cache::flush();

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'server-secret',
        ])->assertRedirect($landing);

        $this->get($landing)->assertOk();

        $this->post(route('logout'));
    }
});

test('a signed-in demo role opening the login page goes straight to its home', function () {
    foreach (demoLandings() as $email => $landing) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect(app(RoleHome::class)->url($user))->toBe($landing);

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect($landing);

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('login'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam?->slug ?? 'missing']))
            ->assertRedirect();
    }
});

test('the signed-in in-charge never lands on the command center or a 403', function () {
    $incharge = User::query()->where('email', 'incharge@tbai.test')->firstOrFail();

    $this->actingAs($incharge)
        ->get(route('login'))
        ->assertRedirect(route('returns.today', 'karachi-west-bmu'));

    $this->actingAs($incharge)
        ->withSession(['url.intended' => route('command.index')])
        ->get(route('login'))
        ->assertRedirect(route('returns.today', 'karachi-west-bmu'));

    $this->actingAs($incharge)
        ->followingRedirects()
        ->get(route('login'))
        ->assertOk();
});

test('the admin can still reach the command center from the sidebar', function () {
    $admin = User::query()->where('email', 'admin@tbai.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('facilities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('homeUrl', route('facilities.index'))
            ->where('nav.groups', fn ($groups) => str_contains(
                (string) json_encode($groups, JSON_UNESCAPED_SLASHES),
                '"href":"'.route('command.index').'"',
            )));

    $this->actingAs($admin)->get(route('command.index'))->assertOk();
});

<?php

use App\Models\User;
use Database\Seeders\LoginSeeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

test('login seeder creates verified accounts that can sign in', function () {
    config([
        'access.admin_email' => 'reviewer@example.com',
        'access.seed_login_password' => 'server-secret',
    ]);

    $this->seed(LoginSeeder::class);

    $admin = User::query()->where('email', 'reviewer@example.com')->first();
    $member = User::query()->where('email', 'member@tbai.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->personalTeam())->not->toBeNull()
        ->and(Hash::check('server-secret', $admin->password))->toBeTrue()
        ->and($member)->not->toBeNull()
        ->and($member->personalTeam())->not->toBeNull();

    $this->post(route('login.store'), [
        'email' => 'reviewer@example.com',
        'password' => 'server-secret',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
});

test('login seeder can be run again without duplicating accounts', function () {
    config(['access.seed_login_password' => 'server-secret']);

    $this->seed(LoginSeeder::class);
    $this->seed(LoginSeeder::class);

    expect(User::query()->where('email', 'admin@tbai.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'member@tbai.test')->count())->toBe(1)
        ->and(User::query()->count())->toBe(2);
});

test('login seeder refuses to use the default password outside local and testing', function () {
    $this->app['env'] = 'production';
    config(['access.seed_login_password' => null]);

    expect(fn () => (new LoginSeeder)->run())->toThrow(
        RuntimeException::class,
        'Set SEED_LOGIN_PASSWORD before seeding login accounts.',
    );
});

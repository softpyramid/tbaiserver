<?php

use App\Models\AccessRequest;
use App\Models\User;

test('the register address shows the request access form', function () {
    $this->get('/register')
        ->assertRedirect('/request-access');
});

test('the request access screen can be rendered', function () {
    $this->get(route('access-requests.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('access-request'));
});

test('an access request is stored for review and does not create an account', function () {
    $response = $this->post(route('access-requests.store'), [
        'name' => 'Ayesha Khan',
        'role' => 'Lab technician',
        'organisation' => 'Thatta BMU',
        'district' => 'Thatta',
        'email' => 'ayesha@example.com',
    ]);

    $response->assertRedirect(route('access-requests.create'));
    $this->assertGuest();

    $this->assertDatabaseHas('access_requests', [
        'name' => 'Ayesha Khan',
        'role' => 'Lab technician',
        'organisation' => 'Thatta BMU',
        'district' => 'Thatta',
        'email' => 'ayesha@example.com',
    ]);

    expect(User::query()->count())->toBe(0);
    expect(AccessRequest::query()->count())->toBe(1);
});

test('an access request requires a name, role, organisation, district, and email', function () {
    $response = $this->from(route('access-requests.create'))
        ->post(route('access-requests.store'), []);

    $response->assertRedirect(route('access-requests.create'));
    $response->assertSessionHasErrors(['name', 'role', 'organisation', 'district', 'email']);
    $this->assertGuest();
    expect(AccessRequest::query()->count())->toBe(0);
});

test('posting to the old register address does not create an account', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/request-access');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

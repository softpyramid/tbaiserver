<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Requests\StoreAccessRequest;
use App\Models\AccessRequest;
use App\Models\User;
use App\Notifications\AccessRequestReceived;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * @return array<string, string>
 */
function accessRequestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ayesha Khan',
        'role' => 'Lab technician',
        'organisation' => 'Thatta BMU',
        'district' => 'Thatta',
        'email' => 'ayesha@example.com',
        'consent' => '1',
    ], $overrides);
}

test('the register address shows the request access form', function () {
    $this->get('/register')
        ->assertRedirect('/request-access');
});

test('the request access screen can be rendered', function () {
    $this->get(route('access-requests.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('access-request')
            ->missing('accessRequest')
            ->missing('accessRequests'));
});

test('an access request is stored for review and does not create an account', function () {
    Notification::fake();
    config(['access.admin_email' => 'reviewer@example.com']);

    $response = $this->post(route('access-requests.store'), accessRequestPayload());

    $response->assertRedirect(route('access-requests.create'));
    $response->assertSessionHas('status', AccessRequestController::RECEIVED_STATUS);
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

    Notification::assertSentOnDemand(
        AccessRequestReceived::class,
        function (AccessRequestReceived $notification, array $channels, object $notifiable): bool {
            $mail = $notification->toMail($notifiable);
            $lines = implode(' ', $mail->introLines);

            return $notifiable->routes['mail'] === 'reviewer@example.com'
                && $mail->subject === 'New TB-AI access request'
                && str_contains($lines, 'Ayesha Khan')
                && str_contains($lines, 'Lab technician')
                && str_contains($lines, 'Thatta BMU')
                && str_contains($lines, 'Thatta')
                && str_contains($lines, 'ayesha@example.com');
        },
    );
});

test('an access request requires a name, role, organisation, district, email, and consent', function () {
    $response = $this->from(route('access-requests.create'))
        ->post(route('access-requests.store'), []);

    $response->assertRedirect(route('access-requests.create'));
    $response->assertSessionHasErrors(['name', 'role', 'organisation', 'district', 'email', 'consent']);
    $this->assertGuest();
    expect(AccessRequest::query()->count())->toBe(0);
});

test('an access request rejects an invalid email and fields that are too long', function (array $overrides, string $field) {
    $response = $this->from(route('access-requests.create'))
        ->post(route('access-requests.store'), accessRequestPayload($overrides));

    $response->assertRedirect(route('access-requests.create'));
    $response->assertSessionHasErrors($field);
    expect(AccessRequest::query()->count())->toBe(0);
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'long email' => [['email' => str_repeat('a', 244).'@example.com'], 'email'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'long role' => [['role' => str_repeat('a', 256)], 'role'],
    'long organisation' => [['organisation' => str_repeat('a', 256)], 'organisation'],
    'long district' => [['district' => str_repeat('a', 256)], 'district'],
    'consent declined' => [['consent' => '0'], 'consent'],
]);

test('a filled honeypot is discarded without storing a request or sending mail', function () {
    Notification::fake();
    config(['access.admin_email' => 'reviewer@example.com']);

    $response = $this->post(route('access-requests.store'), accessRequestPayload([
        StoreAccessRequest::HONEYPOT => 'https://spam.example',
    ]));

    $response->assertRedirect(route('access-requests.create'));
    $response->assertSessionHas('status', AccessRequestController::RECEIVED_STATUS);
    expect(AccessRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('access requests are limited to five per minute for each ip', function () {
    config(['access.admin_email' => null]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('access-requests.store'), accessRequestPayload([
            'email' => "person{$attempt}@example.com",
        ]))->assertRedirect(route('access-requests.create'));
    }

    $this->post(route('access-requests.store'), accessRequestPayload([
        'email' => 'blocked@example.com',
    ]))->assertTooManyRequests();

    expect(AccessRequest::query()->where('email', 'blocked@example.com')->exists())->toBeFalse();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->post(route('access-requests.store'), accessRequestPayload([
            'email' => 'other-ip@example.com',
        ]))
        ->assertRedirect(route('access-requests.create'));

    $this->assertDatabaseHas('access_requests', [
        'email' => 'other-ip@example.com',
    ]);
});

test('stored access requests stay off public pages and are visible only to the configured administrator', function () {
    $stored = AccessRequest::query()->create([
        'name' => 'Hidden Person',
        'role' => 'Lab technician',
        'organisation' => 'Larkana BMU',
        'district' => 'Larkana',
        'email' => 'hidden-requester@example.test',
    ]);

    config(['access.admin_email' => 'reviewer@example.com']);

    foreach ([route('home'), route('privacy'), route('terms'), route('access-requests.create')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('hidden-requester@example.test', false)
            ->assertDontSee('Hidden Person', false);
    }

    $this->get(route('access-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('access-request')
            ->where('status', null)
            ->missing('accessRequest')
            ->missing('accessRequests'));

    $this->get('/access-requests')->assertMethodNotAllowed();
    $this->get('/access-requests/'.$stored->id)->assertNotFound();

    expect(Gate::allows('viewAny', AccessRequest::class))->toBeFalse();
    expect(Gate::allows('view', $stored))->toBeFalse();

    $member = User::factory()->create();

    expect($member->can('viewAny', AccessRequest::class))->toBeFalse();
    expect($member->can('view', $stored))->toBeFalse();

    $admin = User::factory()->create(['email' => 'reviewer@example.com']);

    expect($admin->can('viewAny', AccessRequest::class))->toBeTrue();
    expect($admin->can('view', $stored))->toBeTrue();

    $this->actingAs($member)
        ->get(route('access-requests.create'))
        ->assertOk()
        ->assertDontSee('hidden-requester@example.test', false);

    $this->actingAs($admin)
        ->get(route('access-requests.create'))
        ->assertOk()
        ->assertDontSee('hidden-requester@example.test', false)
        ->assertInertia(fn ($page) => $page->missing('accessRequest')->missing('accessRequests'));
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

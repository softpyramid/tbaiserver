<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Impersonation;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * @return array{0: Facility, 1: Facility}
 */
function impFacilities(?Facility $own = null, ?Facility $other = null): array
{
    static $facilities = [];

    if ($own !== null && $other !== null) {
        $facilities = [$own, $other];
    }

    return $facilities;
}

function impStaffUrl(User $user): string
{
    return route('facilities.members', ['current_team' => $user->fresh()->currentTeam->slug]);
}

/**
 * Builds one person per role the feature cares about.
 *
 * @return array<string, User>
 */
function impCast(): array
{
    $facility = Facility::factory()->create();
    $other = Facility::factory()->create();
    impFacilities($facility, $other);

    $cast = [
        'admin' => User::factory()->create(),
        'country' => User::factory()->create(),
        'sindh' => User::factory()->create(),
        'karachi-west' => User::factory()->create(),
        'incharge' => User::factory()->create(),
        'senior' => User::factory()->create(),
        'technician' => User::factory()->create(),
        'other-incharge' => User::factory()->create(),
        'other-technician' => User::factory()->create(),
    ];

    giveProgrammeRole($cast['admin'], ProgrammeRole::TbaiAdmin);

    ProgrammeAssignment::factory()->create([
        'user_id' => $cast['country']->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => Country::factory()->create()->id,
    ]);
    ProgrammeAssignment::factory()->create([
        'user_id' => $cast['sindh']->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => Province::factory()->create()->id,
    ]);
    ProgrammeAssignment::factory()->create([
        'user_id' => $cast['karachi-west']->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => City::factory()->create()->id,
    ]);

    joinFacility($cast['incharge'], $facility, TeamRole::Owner);
    joinFacility($cast['senior'], $facility, TeamRole::Admin);
    joinFacility($cast['technician'], $facility, TeamRole::Member);
    joinFacility($cast['other-incharge'], $other, TeamRole::Owner);
    joinFacility($cast['other-technician'], $other, TeamRole::Member);

    return $cast;
}

function impStart(User $actor, User $target)
{
    return test()->actingAs($actor)->post(route('impersonation.start', $target));
}

it('lets an admin impersonate everyone except admins, themselves and inactive users', function () {
    $cast = impCast();

    foreach (['country', 'sindh', 'karachi-west', 'incharge', 'senior', 'technician', 'other-incharge'] as $key) {
        impStart($cast['admin'], $cast[$key])->assertRedirect();
        expect(auth()->id())->toBe($cast[$key]->id)
            ->and(session(Impersonation::ORIGINAL_KEY))->toBe($cast['admin']->id);
        $this->post(route('impersonation.stop'))->assertRedirect();
        expect(auth()->id())->toBe($cast['admin']->id);
    }

    $secondAdmin = User::factory()->create();
    giveProgrammeRole($secondAdmin, ProgrammeRole::TbaiAdmin);
    $inactive = User::factory()->create(['deactivated_at' => now()]);
    joinFacility($inactive, Facility::factory()->create(), TeamRole::Member);

    impStart($cast['admin'], $secondAdmin)->assertForbidden();
    impStart($cast['admin'], $cast['admin'])->assertForbidden();
    impStart($cast['admin'], $inactive)->assertForbidden();
});

it('lets an in-charge impersonate lower staff in their own facility only', function () {
    $cast = impCast();

    foreach (['technician', 'senior'] as $key) {
        impStart($cast['incharge'], $cast[$key])->assertRedirect();
        expect(auth()->id())->toBe($cast[$key]->id);
        $this->post(route('impersonation.stop'))->assertRedirect();
        expect(auth()->id())->toBe($cast['incharge']->id);
    }

    foreach (['other-incharge', 'other-technician', 'admin', 'country', 'sindh', 'karachi-west', 'incharge'] as $key) {
        impStart($cast['incharge'], $cast[$key])->assertForbidden();
    }

    expect(session(Impersonation::ORIGINAL_KEY))->toBeNull()
        ->and(AuditLog::where('action', 'impersonation.started')->count())->toBe(2);
});

it('refuses an in-charge who targets someone with another membership or a programme role', function () {
    $cast = impCast();
    [$own, $other] = impFacilities();

    $shared = User::factory()->create();
    joinFacility($shared, $own, TeamRole::Member);
    joinFacility($shared, $other, TeamRole::Member);
    impStart($cast['incharge'], $shared)->assertForbidden();

    $viewer = User::factory()->create();
    joinFacility($viewer, $own, TeamRole::Member);
    giveProgrammeRole($viewer, ProgrammeRole::CountryViewer);
    impStart($cast['incharge'], $viewer)->assertForbidden();

    $inactive = User::factory()->create(['deactivated_at' => now()]);
    joinFacility($inactive, $own, TeamRole::Member);
    impStart($cast['incharge'], $inactive)->assertForbidden();

    $coOwner = User::factory()->create();
    joinFacility($coOwner, $own, TeamRole::Owner);
    impStart($cast['incharge'], $coOwner)->assertForbidden();
});

it('forbids viewers, senior technicians and technicians from impersonating anyone', function () {
    $cast = impCast();

    foreach (['country', 'sindh', 'karachi-west', 'senior', 'technician', 'other-technician'] as $actor) {
        foreach (['technician', 'senior', 'incharge', 'other-technician'] as $target) {
            if ($actor === $target) {
                continue;
            }

            impStart($cast[$actor], $cast[$target])->assertForbidden();
        }

        expect(session(Impersonation::ORIGINAL_KEY))->toBeNull()
            ->and(auth()->id())->toBe($cast[$actor]->id);
    }

    expect(AuditLog::where('action', 'impersonation.started')->count())->toBe(0);
});

it('redirects guests to log in', function () {
    $user = User::factory()->create();

    $this->post(route('impersonation.start', $user))->assertRedirect(route('login'));
    $this->post(route('impersonation.stop'))->assertRedirect(route('login'));
});

it('blocks nested impersonation', function () {
    $cast = impCast();

    impStart($cast['admin'], $cast['incharge'])->assertRedirect();
    $this->post(route('impersonation.start', $cast['technician']))->assertForbidden();

    expect(auth()->id())->toBe($cast['incharge']->id)
        ->and(session(Impersonation::ORIGINAL_KEY))->toBe($cast['admin']->id);

    $this->post(route('impersonation.stop'));
    expect(auth()->id())->toBe($cast['admin']->id);
});

it('audits start and stop with actor, target, time and IP', function () {
    $cast = impCast();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
    impStart($cast['admin'], $cast['incharge']);
    $this->post(route('impersonation.stop'));

    $started = AuditLog::where('action', 'impersonation.started')->sole();
    $stopped = AuditLog::where('action', 'impersonation.stopped')->sole();

    expect($started->user_id)->toBe($cast['admin']->id)
        ->and($started->properties['target_id'])->toBe($cast['incharge']->id)
        ->and($started->properties['ip'])->toBe('203.0.113.9')
        ->and($started->created_at)->not->toBeNull()
        ->and($stopped->user_id)->toBe($cast['admin']->id)
        ->and($stopped->properties['target_id'])->toBe($cast['incharge']->id)
        ->and($stopped->properties['ip'])->toBe('203.0.113.9')
        ->and($stopped->created_at)->not->toBeNull();
});

it('shows the impersonation events on the audit log page', function () {
    $cast = impCast();

    impStart($cast['admin'], $cast['incharge']);
    $this->post(route('impersonation.stop'));

    $this->actingAs($cast['admin'])->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertSee('impersonation.started', false)
        ->assertSee('impersonation.stopped', false)
        ->assertSee('IP ', false);
});

it('tags actions done while impersonating with the original user', function () {
    $cast = impCast();

    impStart($cast['admin'], $cast['incharge']);
    app(AuditLogger::class)->write(auth()->user(), 'qa.probe', $cast['technician']);
    $probe = AuditLog::where('action', 'qa.probe')->sole();

    expect($probe->user_id)->toBe($cast['incharge']->id)
        ->and($probe->properties['impersonated_by'])->toBe($cast['admin']->id);
});

it('shares the banner data on every page while impersonating and not otherwise', function () {
    $cast = impCast();

    $this->actingAs($cast['admin'])->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->where('impersonation', null));

    impStart($cast['admin'], $cast['incharge']);

    foreach ([impStaffUrl($cast['incharge']), route('profile.edit')] as $url) {
        $this->get($url)->assertInertia(fn ($page) => $page
            ->where('impersonation.name', $cast['incharge']->name)
            ->where('impersonation.banner', 'Viewing as '.$cast['incharge']->name.'.')
            ->where('impersonation.returnLabel', 'Return to my account')
            ->where('impersonation.stopLabel', 'Stop')
            ->where('impersonation.stopUrl', route('impersonation.stop')));
    }
});

it('restores the original session and sends the actor back where they started', function () {
    $cast = impCast();

    $this->actingAs($cast['admin'])->withHeader('Referer', route('admin.audit-log.index'))
        ->post(route('impersonation.start', $cast['incharge']));
    $this->post(route('impersonation.stop'))->assertRedirect(route('admin.audit-log.index'));

    expect(auth()->id())->toBe($cast['admin']->id)
        ->and(session(Impersonation::ORIGINAL_KEY))->toBeNull()
        ->and(session(Impersonation::RETURN_KEY))->toBeNull();

    $this->actingAs($cast['admin'])->withHeader('Referer', 'https://elsewhere.example/page')
        ->post(route('impersonation.start', $cast['incharge']));
    $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users.index'));

    $this->post(route('impersonation.stop'))->assertForbidden();
});

it('returns an in-charge to the staff list after viewing as a technician', function () {
    $cast = impCast();

    $this->actingAs($cast['incharge'])->withHeader('Referer', impStaffUrl($cast['incharge']))
        ->post(route('impersonation.start', $cast['technician']));
    $this->post(route('impersonation.stop'))->assertRedirect(impStaffUrl($cast['incharge']));

    expect(auth()->id())->toBe($cast['incharge']->id);
});

it('does not leave a stale intended url and keeps the no-store header', function () {
    $cast = impCast();

    $response = $this->actingAs($cast['admin'])->withSession(['url.intended' => 'http://localhost/admin/audit-log'])
        ->post(route('impersonation.start', $cast['incharge']));

    expect(session('url.intended'))->toBeNull();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $stop = $this->post(route('impersonation.stop'));
    expect($stop->headers->get('Cache-Control'))->toContain('no-store')
        ->and(session('url.intended'))->toBeNull();
});

it('does not fire login events or notifications on start and stop', function () {
    $cast = impCast();
    Event::fake([Login::class]);
    NotificationFacade::fake();

    impStart($cast['admin'], $cast['incharge']);
    $this->post(route('impersonation.stop'));

    Event::assertNotDispatched(Login::class);
    NotificationFacade::assertNothingSent();
});

it('blocks password, email and security changes while impersonating', function () {
    $cast = impCast();
    $target = $cast['incharge'];
    $passwordBefore = $target->password;
    $emailBefore = $target->email;

    impStart($cast['admin'], $target);

    $passkeyId = DB::table('passkeys')->insertGetId([
        'user_id' => $target->id,
        'name' => 'Laptop',
        'credential_id' => 'abc',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $calls = [
        ['patch', route('profile.update'), ['name' => 'Changed', 'email' => 'changed@example.test']],
        ['put', route('user-password.update'), ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1']],
        ['get', route('security.edit'), []],
        ['post', route('two-factor.enable'), []],
        ['delete', route('two-factor.disable'), []],
        ['post', route('two-factor.confirm'), ['code' => '123456']],
        ['get', route('two-factor.recovery-codes'), []],
        ['post', route('two-factor.regenerate-recovery-codes'), []],
        ['post', route('passkey.store'), []],
        ['delete', route('passkey.destroy', $passkeyId), []],
        ['post', route('verification.send'), []],
    ];

    foreach ($calls as [$method, $url, $data]) {
        $this->{$method}($url, $data)->assertForbidden();
    }

    $fresh = $target->fresh();
    expect($fresh->password)->toBe($passwordBefore)
        ->and($fresh->email)->toBe($emailBefore)
        ->and($fresh->name)->not->toBe('Changed')
        ->and($fresh->two_factor_secret)->toBeNull()
        ->and(Hash::check('password', $fresh->password))->toBeTrue()
        ->and(DB::table('passkeys')->where('id', $passkeyId)->exists())->toBeTrue();
});

it('shows a clear message when a security change is blocked', function () {
    $cast = impCast();

    impStart($cast['admin'], $cast['incharge']);

    $this->put(route('user-password.update'), ['password' => 'x'])
        ->assertForbidden()
        ->assertSee(__('entry.impersonation_blocked'), false);
});

it('allows the same security changes again after stopping', function () {
    $cast = impCast();

    impStart($cast['admin'], $cast['incharge']);
    $this->post(route('impersonation.stop'));

    expect($this->get(route('security.edit'))->status())->not->toBe(403);
});

it('sends the target no mail or notifications while impersonating', function () {
    $cast = impCast();
    config(['mail.default' => 'array']);

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Probe')->line('Probe');
        }
    };

    $transport = fn () => app('mailer')->getSymfonyTransport();

    impStart($cast['admin'], $cast['incharge']);
    $cast['incharge']->notify($notification);
    NotificationFacade::route('mail', $cast['incharge']->email)->notify($notification);
    expect($transport()->messages())->toHaveCount(0);

    $this->post(route('impersonation.stop'));
    $cast['incharge']->notify($notification);
    expect($transport()->messages())->toHaveCount(1);
});

it('does not touch the target last seen time while impersonating', function () {
    $cast = impCast();
    $cast['incharge']->forceFill(['last_seen_at' => null])->save();

    impStart($cast['admin'], $cast['incharge']);
    $this->get(route('dashboard'));

    expect($cast['incharge']->fresh()->last_seen_at)->toBeNull();
});

it('records a stop when the actor signs out while impersonating', function () {
    $cast = impCast();

    impStart($cast['admin'], $cast['incharge']);
    $this->post(route('logout'));

    $stopped = AuditLog::where('action', 'impersonation.stopped')->sole();

    expect($stopped->user_id)->toBe($cast['admin']->id)
        ->and($stopped->properties['reason'])->toBe('sign_out');
});

it('shows the control to admins and in-charges only', function () {
    $cast = impCast();
    $page = fn ($response) => str_replace('\\/', '/', html_entity_decode($response->getContent()));

    $html = $page($this->actingAs($cast['admin'])->get(route('admin.users.index')));
    expect($html)->toContain(route('impersonation.start', $cast['incharge'], false))
        ->and($html)->not->toContain(route('impersonation.start', $cast['admin'], false));

    $html = $page($this->actingAs($cast['incharge'])->get(impStaffUrl($cast['incharge'])));
    expect($html)->toContain(route('impersonation.start', $cast['technician'], false))
        ->and($html)->not->toContain(route('impersonation.start', $cast['incharge'], false));

    $html = $page($this->actingAs($cast['senior'])->get(impStaffUrl($cast['senior'])));
    expect($html)->not->toContain('impersonation/');
});

it('keeps Staff hidden from technicians', function () {
    $cast = impCast();

    $this->actingAs($cast['technician'])->get(impStaffUrl($cast['technician']))->assertForbidden();
});

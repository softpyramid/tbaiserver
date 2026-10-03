<?php

namespace App\Support;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * "Log in as this user".
 *
 * Who may impersonate whom (the rule the policy uses):
 *
 * - The actor must manage the target's facility or scope AND strictly outrank
 *   the target. Rank is the existing role hierarchy: a TB-AI admin sits above
 *   every other account; a facility in-charge (team role Owner) sits above the
 *   senior technicians and technicians (TeamRole::level) of a facility they
 *   own. Programme viewers (country, provincial, city) read data and manage
 *   nobody, and senior technicians and technicians manage nobody, so none of
 *   them may impersonate.
 * - Never a TB-AI admin, never yourself, never an inactive account.
 * - Never while already impersonating (no nesting).
 * - An in-charge may only impersonate a person whose every facility
 *   membership is in a facility the in-charge owns, at a lower role, and who
 *   holds no programme role.
 *
 * The original user id lives in the session. Starting and stopping switch the
 * session user directly, so no login event, login notification or
 * verification mail is triggered.
 */
class Impersonation
{
    public const ORIGINAL_KEY = 'impersonation.original_id';

    public const RETURN_KEY = 'impersonation.return_url';

    public function __construct(private readonly AuditLogger $audit) {}

    public function active(?Request $request = null): bool
    {
        return $this->originalId($request) !== null;
    }

    public function originalId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::ORIGINAL_KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * The rule behind the policy. Does not look at the session; the policy
     * adds the "no nesting" check.
     */
    public function outranks(User $actor, User $target): bool
    {
        if ($actor->is($target) || $target->deactivated_at !== null || $actor->deactivated_at !== null) {
            return false;
        }

        if ($target->isTbaiAdmin()) {
            return false;
        }

        if ($actor->isTbaiAdmin()) {
            return true;
        }

        if ($target->programmeAssignments()->exists()) {
            return false;
        }

        $memberships = $target->teamMemberships()
            ->with('team.facility')
            ->get()
            ->filter(fn (Membership $membership): bool => ! $membership->team->is_personal && $membership->team->facility !== null);

        if ($memberships->isEmpty()) {
            return false;
        }

        return $memberships->every(function (Membership $membership) use ($actor): bool {
            $actorRole = $actor->teamRole($membership->team);

            return $actorRole === TeamRole::Owner
                && $actorRole->level() > $membership->role->level();
        });
    }

    /**
     * Start impersonating. The caller has already authorised it with the policy.
     */
    public function start(Request $request, User $actor, User $target): void
    {
        $this->audit->write($actor, 'impersonation.started', $target, $this->details($request, $actor, $target));

        $session = $request->session();
        $session->put(self::ORIGINAL_KEY, $actor->id);
        $session->put(self::RETURN_KEY, $this->safeReturnUrl($request));
        $session->forget('url.intended');

        $this->switchTo($request, $target);
    }

    /**
     * Restore the original session. Returns the url to open next: the page
     * the person started from, else People and access for a TB-AI admin or the
     * role home for anyone else. Returns null when the original account can no
     * longer sign in and the whole session ended.
     */
    public function stop(Request $request, User $current, string $reason = 'stopped'): ?string
    {
        $originalId = $this->originalId($request);
        abort_if($originalId === null, 403);

        $session = $request->session();
        $returnUrl = $session->get(self::RETURN_KEY);
        $original = User::query()->find($originalId);

        if ($original instanceof User) {
            $this->audit->write($original, 'impersonation.stopped', $current, $this->details($request, $original, $current) + ['reason' => $reason]);
        }

        $session->forget([self::ORIGINAL_KEY, self::RETURN_KEY, 'url.intended']);

        if (! $original instanceof User || $original->deactivated_at !== null) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return null;
        }

        $this->switchTo($request, $original);

        if (is_string($returnUrl) && app(IntendedUrl::class)->permits($original, $returnUrl)) {
            return $returnUrl;
        }

        return $original->isTbaiAdmin()
            ? route('admin.users.index')
            : app(RoleHome::class)->url($original);
    }

    /**
     * Record the end of an impersonation that ended by signing out.
     */
    public function recordSignOut(Request $request, User $current): void
    {
        $originalId = $this->originalId($request);

        if ($originalId === null) {
            return;
        }

        $original = User::query()->find($originalId);

        if ($original instanceof User) {
            $this->audit->write($original, 'impersonation.stopped', $current, $this->details($request, $original, $current) + ['reason' => 'sign_out']);
        }

        $request->session()->forget([self::ORIGINAL_KEY, self::RETURN_KEY]);
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Request $request, User $actor, User $target): array
    {
        return [
            'actor_id' => $actor->id,
            'target_id' => $target->id,
            'ip' => $request->ip(),
            'at' => now()->toIso8601String(),
        ];
    }

    /**
     * Switch the session user without Fortify's login event, so nobody gets a
     * sign-in notice or verification mail, and drop the other person's pages
     * from the browser history.
     */
    private function switchTo(Request $request, User $user): void
    {
        $guard = Auth::guard('web');
        $request->session()->put($guard->getName(), $user->getAuthIdentifier());
        $guard->setUser($user);
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        Inertia::clearHistory();
    }

    private function safeReturnUrl(Request $request): string
    {
        $from = url()->previous();
        $parts = parse_url($from);

        if ($parts === false || ($parts['host'] ?? $request->getHost()) !== $request->getHost()) {
            return route('admin.users.index');
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return url($path);
    }
}

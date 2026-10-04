<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSecurityChangesWhileImpersonating
{
    /**
     * Routes that change or reveal the sign-in security of the account being
     * viewed: profile name and email, account deletion, password, two-factor
     * authentication, recovery codes, passkeys, and mail that goes to the
     * account holder.
     *
     * @var list<string>
     */
    public const BLOCKED = [
        'profile.update',
        'profile.destroy',
        'user-password.update',
        'security.edit',
        'verification.send',
        'password.email',
        'password.update',
        'two-factor.enable',
        'two-factor.disable',
        'two-factor.confirm',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'passkey.store',
        'passkey.destroy',
        'passkey.registration-options',
        'passkey.confirm',
        'passkey.confirm-options',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if ($name !== null && in_array($name, self::BLOCKED, true) && app(Impersonation::class)->active($request)) {
            abort(403, __('entry.impersonation_blocked'));
        }

        return $next($request);
    }
}

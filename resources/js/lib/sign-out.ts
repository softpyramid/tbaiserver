import { router } from '@inertiajs/react';

/**
 * Drop everything the browser kept for the signed-in person before the logout
 * request goes out. The server clears the encrypted history on the redirect.
 * Callers pair this with a replace visit so private pages are not left in the
 * history stack.
 */
export function clearClientSession(): void {
    router.flushAll();
}

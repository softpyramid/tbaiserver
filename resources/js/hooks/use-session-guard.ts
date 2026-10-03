import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * A page restored from the back/forward cache never asks the server anything.
 * When that happens on a signed-in page, ask again: a signed-out session gets
 * redirected to /login instead of drawing the private page.
 */
export function useSessionGuard(): void {
    useEffect(() => {
        const onPageShow = (event: PageTransitionEvent) => {
            if (event.persisted) {
                router.reload();
            }
        };

        window.addEventListener('pageshow', onPageShow);

        return () => window.removeEventListener('pageshow', onPageShow);
    }, []);
}

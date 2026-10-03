import { router, usePage } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Shown on every layout while someone is viewing as another person. It sticks
 * to the top of the page so it stays on screen on desktop and phone.
 */
export function ImpersonationBanner() {
    const impersonation = usePage().props.impersonation;
    const [stopping, setStopping] = useState(false);

    if (!impersonation) {
        return null;
    }

    return (
        <div
            role="status"
            data-test="impersonation-banner"
            className="sticky top-0 z-[60] flex w-full flex-wrap items-center justify-between gap-x-4 gap-y-2 bg-amber-300 px-4 py-2 text-sm text-neutral-950"
        >
            <p className="flex min-w-0 items-center gap-2">
                <UserRoundCog aria-hidden="true" className="size-4 shrink-0" />
                <span className="min-w-0 break-words">
                    <strong>{impersonation.banner}</strong>{' '}
                    {impersonation.returnLabel}
                </span>
            </p>
            <Button
                type="button"
                size="sm"
                data-test="impersonation-stop"
                disabled={stopping}
                className="bg-neutral-950 text-white hover:bg-neutral-800"
                onClick={() =>
                    router.post(
                        impersonation.stopUrl,
                        {},
                        {
                            onStart: () => setStopping(true),
                            onFinish: () => setStopping(false),
                        },
                    )
                }
            >
                {impersonation.stopLabel}
            </Button>
        </div>
    );
}

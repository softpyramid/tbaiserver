import type { ReactNode } from 'react';
import { Spinner } from '@/components/spinner';
import { Button } from '@/components/ui/button';
import { LOAD_ERROR_TEXT, LOADING_TEXT, RETRY_TEXT } from '@/lib/loading';
import { cn } from '@/lib/utils';

/**
 * Dims its children while new data loads and overlays a spinner, or an
 * error with Retry. The overlay is absolutely positioned, so the children
 * keep their size. `className` applies to the element around the children.
 */
export function PanelLoading({
    pending,
    error = false,
    onRetry,
    className,
    children,
}: {
    pending: boolean;
    error?: boolean;
    onRetry?: () => void;
    className?: string;
    children: ReactNode;
}) {
    const busy = pending || error;
    const message = error ? LOAD_ERROR_TEXT : pending ? LOADING_TEXT : '';

    return (
        <div
            className="relative min-w-0"
            aria-busy={pending}
            data-test="panel-loading"
            data-state={error ? 'error' : pending ? 'pending' : 'idle'}
        >
            <div
                className={cn(
                    'transition-opacity duration-150 motion-reduce:transition-none',
                    busy && 'pointer-events-none opacity-50 select-none',
                    className,
                )}
            >
                {children}
            </div>

            <div
                role="status"
                aria-live="polite"
                className={busy ? 'absolute inset-0 z-10' : 'sr-only'}
            >
                {busy ? (
                    <div className="sticky top-24 mx-auto mt-12 flex w-fit items-center gap-3 rounded-lg border bg-background px-4 py-3 text-sm shadow-sm">
                        {error ? (
                            <>
                                <span>{message}</span>
                                {onRetry ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={onRetry}
                                    >
                                        {RETRY_TEXT}
                                    </Button>
                                ) : null}
                            </>
                        ) : (
                            <>
                                <Spinner size="sm" decorative />
                                <span>{message}</span>
                            </>
                        )}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

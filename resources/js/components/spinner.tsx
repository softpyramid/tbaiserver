import { Spinner as BaseSpinner } from '@/components/ui/spinner';
import { LOADING_TEXT } from '@/lib/loading';
import { cn } from '@/lib/utils';

const sizes = {
    sm: 'size-4',
    md: 'size-6',
    lg: 'size-8',
} as const;

export type SpinnerSize = keyof typeof sizes;

/**
 * Rotating loader with an accessible label. Pass `decorative` when visible
 * text next to it already says what is loading. Stops rotating (but stays
 * visible) for prefers-reduced-motion.
 */
export function Spinner({
    size = 'md',
    label = LOADING_TEXT,
    decorative = false,
    className,
}: {
    size?: SpinnerSize;
    label?: string;
    decorative?: boolean;
    className?: string;
}) {
    return (
        <BaseSpinner
            className={cn(sizes[size], 'motion-reduce:animate-none', className)}
            role={decorative ? undefined : 'status'}
            aria-label={decorative ? undefined : label}
            aria-hidden={decorative ? true : undefined}
        />
    );
}

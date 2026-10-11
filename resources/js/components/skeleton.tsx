import type { CSSProperties } from 'react';
import { Skeleton as BaseSkeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/*
 * Placeholder blocks shaped like the content they stand in for. They are
 * aria-hidden: pair them with a status message (PanelLoading or a
 * visually hidden "Loading..."). Pulsing stops for prefers-reduced-motion.
 *
 * Used as the Inertia <Deferred> fallbacks on /command (notification,
 * estimate, months) in resources/js/pages/command/index.tsx.
 */

export function Skeleton({
    className,
    style,
}: {
    className?: string;
    style?: CSSProperties;
}) {
    return (
        <BaseSkeleton
            aria-hidden="true"
            className={cn('motion-reduce:animate-none', className)}
            style={style}
        />
    );
}

/** A KPI or summary card: label, figure and optional detail lines. */
export function SkeletonCard({
    lines = 0,
    className,
}: {
    lines?: number;
    className?: string;
}) {
    return (
        <div
            aria-hidden="true"
            data-test="skeleton-card"
            className={cn('min-w-0 rounded-lg border p-3', className)}
        >
            <Skeleton className="h-4 w-1/2" />
            <Skeleton className="mt-2 h-6 w-1/3" />
            {Array.from({ length: lines }, (_, i) => (
                <Skeleton key={i} className="mt-3 h-3 w-full" />
            ))}
        </div>
    );
}

/** A ring gauge placeholder of the given diameter in pixels. */
export function SkeletonRing({
    size = 96,
    className,
}: {
    size?: number;
    className?: string;
}) {
    return (
        <div
            aria-hidden="true"
            className={cn('flex flex-col items-center gap-2', className)}
        >
            <div
                className="animate-pulse rounded-full border-[10px] border-primary/10 motion-reduce:animate-none"
                style={{ width: size, height: size }}
            />
            <Skeleton className="h-3" style={{ width: size * 0.8 }} />
        </div>
    );
}

/** `rows` x `columns` placeholder table rows. */
export function SkeletonTableRows({
    rows,
    columns,
    rowHeight = 32,
    className,
}: {
    rows: number;
    columns: number;
    rowHeight?: number;
    className?: string;
}) {
    return (
        <div
            aria-hidden="true"
            data-test="skeleton-rows"
            className={cn('flex flex-col', className)}
        >
            {Array.from({ length: rows }, (_, r) => (
                <div
                    key={r}
                    className="grid items-center gap-3 border-b px-2"
                    style={{
                        gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
                        height: rowHeight,
                    }}
                >
                    {Array.from({ length: columns }, (_, c) => (
                        <Skeleton
                            key={c}
                            className="h-3"
                            style={{ width: c === 0 ? '80%' : '55%' }}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}

const BAR_HEIGHTS = [45, 62, 38, 74, 56, 82, 48, 66, 58, 70];

/** A bar or line chart placeholder: bars of varying height on a baseline. */
export function SkeletonChart({
    height = 200,
    bars = BAR_HEIGHTS.length,
    className,
}: {
    height?: number;
    bars?: number;
    className?: string;
}) {
    return (
        <div
            aria-hidden="true"
            data-test="skeleton-chart"
            className={cn('flex items-end gap-2 border-b pb-px', className)}
            style={{ height }}
        >
            {Array.from({ length: bars }, (_, i) => (
                <Skeleton
                    key={i}
                    className="flex-1 rounded-b-none"
                    style={{
                        height: `${BAR_HEIGHTS[i % BAR_HEIGHTS.length]}%`,
                    }}
                />
            ))}
        </div>
    );
}

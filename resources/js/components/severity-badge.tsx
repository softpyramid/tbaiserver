import { Info, TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import { type SeverityTone, severityLabel, severityTone } from '@/lib/severity';

/** Solid badge colours. White on red-700 and sky-700, amber-950 on amber-400: all AA in light and dark mode. */
const BADGE: Record<SeverityTone, string> = {
    critical: 'bg-red-700 text-white',
    warning: 'bg-amber-400 text-amber-950',
    info: 'bg-sky-700 text-white',
};

/** Light tint and a 4px left border in the severity colour. Body text keeps the normal foreground colour. */
const CARD: Record<SeverityTone, string> = {
    critical:
        'border-l-4 border-l-red-600 bg-red-50 dark:border-l-red-500 dark:bg-red-950/40',
    warning:
        'border-l-4 border-l-amber-500 bg-amber-50 dark:border-l-amber-400 dark:bg-amber-950/40',
    info: 'border-l-4 border-l-sky-600 bg-sky-50 dark:border-l-sky-400 dark:bg-sky-950/40',
};

export function severityCardClass(severity: string): string {
    return CARD[severityTone(severity)];
}

export function severityBadgeClass(severity: string): string {
    return BADGE[severityTone(severity)];
}

export function SeverityIcon({
    severity,
    className,
}: {
    severity: string;
    className?: string;
}) {
    const Icon = severityTone(severity) === 'info' ? Info : TriangleAlert;

    return (
        <Icon
            aria-hidden="true"
            className={cn('size-3.5 shrink-0', className)}
        />
    );
}

export function SeverityBadge({
    severity,
    className,
}: {
    severity: string;
    className?: string;
}) {
    return (
        <span
            data-test="severity-badge"
            data-severity={severityTone(severity)}
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                BADGE[severityTone(severity)],
                className,
            )}
        >
            <SeverityIcon severity={severity} />
            {severityLabel(severity)}
        </span>
    );
}

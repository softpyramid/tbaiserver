import { TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The demonstration notice on every simulated diagnosis page. Stays visible
 * when the report is printed.
 */
export function DiagnosisDemoBanner({
    text,
    className,
}: {
    text: string;
    className?: string;
}) {
    return (
        <div
            role="note"
            data-test="diagnosis-demo-banner"
            className={cn(
                'diagnosis-banner flex items-start gap-2 rounded-lg border-2 border-amber-600 bg-amber-50 p-3 text-base font-semibold text-amber-950 dark:bg-amber-950 dark:text-amber-50',
                className,
            )}
        >
            <TriangleAlert
                aria-hidden="true"
                className="mt-0.5 size-5 shrink-0"
            />
            <span>{text}</span>
        </div>
    );
}

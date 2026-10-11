import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const DEVELOPER_URL = 'https://softpyramid.com';

/**
 * A small, muted credit for the technology partner. Only the company name
 * links out. Colour, size and spacing can be overridden through className.
 */
export function SoftPyramidCredit({
    variant = 'developed',
    compact = false,
    className,
}: {
    variant?: 'developed' | 'partner';
    compact?: boolean;
    className?: string;
}) {
    const { t } = useEntryI18n();
    const template = t(
        variant === 'partner' ? 'credit_partner' : 'credit_developed_by',
    );
    const [before, after = ''] = template.split(':company');

    return (
        <p
            className={cn(
                'text-xs text-muted-foreground',
                compact
                    ? 'text-[11px] leading-4 whitespace-nowrap'
                    : 'text-balance',
                className,
            )}
        >
            {before}
            <a
                href={DEVELOPER_URL}
                target="_blank"
                rel="noopener"
                className="rounded-sm underline-offset-2 hover:underline focus-visible:underline focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none"
            >
                {t('credit_company')}
                <span className="sr-only"> {t('credit_new_tab')}</span>
            </a>
            {after}
        </p>
    );
}

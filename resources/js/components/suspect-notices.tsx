import { CircleCheck, Info, ShieldAlert, TriangleAlert } from 'lucide-react';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Amber notice for draft weights. Icon plus text, never colour alone. */
export function SuspectDraftNotice({ text }: { text: string | null }) {
    if (!text) {
        return null;
    }

    return (
        <p
            className="flex items-start gap-2 rounded-xl border border-amber-500 bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
            data-test="suspect-draft-notice"
            role="note"
        >
            <TriangleAlert
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            <span>{text}</span>
        </p>
    );
}

export function SuspectPrivacyNote() {
    const { t } = useEntryI18n();

    return (
        <p
            className="flex items-start gap-2 rounded-xl border bg-card p-3 text-sm text-foreground"
            data-test="suspect-privacy-note"
        >
            <ShieldAlert
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            <span>{t('suspect_privacy_note')}</span>
        </p>
    );
}

export function SuspectUnavailable({ text }: { text: string }) {
    return (
        <p
            className="flex items-start gap-2 rounded-xl border bg-card p-4 text-base text-foreground"
            data-test="suspect-unavailable"
            role="status"
        >
            <Info aria-hidden="true" className="mt-1 size-4 shrink-0" />
            <span>{text}</span>
        </p>
    );
}

/** Refer flag: red with an icon and words, or a neutral "no referral" line. */
export function SuspectReferFlag({
    refer,
    size = 'sm',
}: {
    refer: boolean;
    size?: 'sm' | 'lg';
}) {
    const { t } = useEntryI18n();

    if (refer) {
        return (
            <span
                data-test="suspect-refer"
                className={cn(
                    'inline-flex w-fit items-center gap-1.5 rounded-md bg-red-700 font-semibold text-white',
                    size === 'lg'
                        ? 'px-3 py-2 text-base'
                        : 'px-2 py-0.5 text-xs',
                )}
            >
                <TriangleAlert
                    aria-hidden="true"
                    className={size === 'lg' ? 'size-5' : 'size-3.5'}
                />
                {t('suspect_refer')}
            </span>
        );
    }

    return (
        <span
            data-test="suspect-no-referral"
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-md border font-medium text-foreground',
                size === 'lg' ? 'px-3 py-2 text-base' : 'px-2 py-0.5 text-xs',
            )}
        >
            <CircleCheck
                aria-hidden="true"
                className={size === 'lg' ? 'size-5' : 'size-3.5'}
            />
            {t('suspect_no_referral')}
        </span>
    );
}

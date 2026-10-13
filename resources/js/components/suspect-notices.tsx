import {
    CircleCheck,
    CirclePause,
    ShieldAlert,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
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

/**
 * Screening is paused: no servable rule set. Existing screenings stay
 * readable; the caller hides every way to start a new one.
 */
export function SuspectUnavailable({
    text,
    children,
}: {
    text: string;
    children?: ReactNode;
}) {
    const { t } = useEntryI18n();

    return (
        <div
            className="flex items-start gap-3 rounded-xl border border-amber-500 bg-amber-50 p-4 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
            data-test="suspect-unavailable"
            role="status"
        >
            <CirclePause
                aria-hidden="true"
                className="mt-0.5 size-5 shrink-0"
            />
            <div className="flex flex-col gap-1">
                <p className="text-base font-semibold">
                    {t('suspect_paused_title')}
                </p>
                <p className="text-sm">{text}</p>
                {children}
            </div>
        </div>
    );
}

/** Marks a screening scored with a rule set that was not yet published. */
export function SuspectDraftBadge() {
    const { t } = useEntryI18n();

    return (
        <span
            className="inline-flex w-fit items-center gap-1 rounded-md border border-amber-500 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
            data-test="suspect-draft-badge"
            title={t('suspect_draft_badge_hint')}
        >
            <TriangleAlert aria-hidden="true" className="size-3.5" />
            {t('suspect_draft_badge')}
            <span className="sr-only">: {t('suspect_draft_badge_hint')}</span>
        </span>
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

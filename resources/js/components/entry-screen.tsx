import type { ReactNode } from 'react';
import { useKeyboardInset } from '@/hooks/use-keyboard-inset';
import { useEntryI18n } from '@/lib/i18n';

type Props = {
    title: string;
    facilityName: string;
    dateLabel: string;
    action?: ReactNode;
    /** The page already shows its own title, so skip the inner header card. */
    headerless?: boolean;
    children: ReactNode;
};

export default function EntryScreen({
    title,
    facilityName,
    dateLabel,
    action,
    headerless = false,
    children,
}: Props) {
    const { dir } = useEntryI18n();

    useKeyboardInset();

    return (
        <div
            className="entry-screen mx-auto flex w-full max-w-lg min-w-0 flex-col overflow-x-clip text-base"
            dir={dir}
        >
            {headerless ? null : (
                <header
                    className="sticky top-0 z-20 border-b bg-background/95 backdrop-blur"
                    style={{
                        paddingTop: 'max(0.75rem, env(safe-area-inset-top))',
                        paddingLeft: 'max(1rem, env(safe-area-inset-left))',
                        paddingRight: 'max(1rem, env(safe-area-inset-right))',
                        paddingBottom: '0.75rem',
                    }}
                >
                    <p className="text-base font-semibold text-foreground">
                        {facilityName}
                    </p>
                    <p className="text-base text-slate-700 dark:text-slate-200">
                        {dateLabel}
                    </p>
                    <h1 className="text-base font-semibold">{title}</h1>
                </header>
            )}
            <div
                className="flex flex-col gap-4 py-4"
                style={{
                    paddingLeft: 'max(1rem, env(safe-area-inset-left))',
                    paddingRight: 'max(1rem, env(safe-area-inset-right))',
                    paddingBottom: action
                        ? 'calc(7rem + var(--entry-tab-offset, 0px))'
                        : 'calc(1rem + var(--entry-tab-offset, 0px))',
                }}
            >
                {children}
            </div>
            {action ? (
                <div
                    className="entry-save-bar fixed z-30 border-t bg-background py-3"
                    style={{
                        bottom: 'calc(var(--keyboard-inset, 0px) + var(--entry-tab-offset, 0px))',
                        paddingLeft: 'max(1rem, env(safe-area-inset-left))',
                        paddingRight: 'max(1rem, env(safe-area-inset-right))',
                    }}
                >
                    <div className="mx-auto w-full max-w-lg">{action}</div>
                </div>
            ) : null}
        </div>
    );
}

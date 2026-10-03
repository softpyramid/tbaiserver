import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type ShellTab = {
    id: string;
    label: string;
    href: string;
    current: boolean;
    badge?: number | null;
};

export type ShellTile = {
    key: string;
    label: string;
    figure: string;
    caption: string;
};

type Props = {
    /** Page title. Also used for the browser tab unless headTitle is given. */
    title: string;
    headTitle?: string;
    /** One line on what this page is for. */
    description?: string | null;
    /** Link tabs for pages that share one sidebar item. */
    tabs?: ShellTab[];
    tabsLabel?: string;
    /** The one primary action, shown top right. */
    action?: ReactNode;
    /** Summary tiles under the header. */
    tiles?: ShellTile[];
    /** Small line above the title, for example a date or a scope. */
    eyebrow?: ReactNode;
    /** Breadcrumbs or a back link, shown above the eyebrow. */
    crumbs?: ReactNode;
    children: ReactNode;
};

/**
 * The one page frame for signed-in pages: title and purpose, a tab strip,
 * one primary action at the top right, summary tiles, then full-width content.
 */
export default function PageShell({
    title,
    headTitle,
    description,
    tabs = [],
    tabsLabel,
    action,
    tiles = [],
    eyebrow,
    crumbs,
    children,
}: Props) {
    return (
        <>
            <Head title={headTitle ?? title} />
            <div
                className="mx-auto flex w-full max-w-[88rem] min-w-0 flex-col gap-5 px-4 py-6 md:px-6"
                data-test="page-shell"
            >
                <header className="flex min-w-0 flex-col gap-3">
                    {crumbs}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            {eyebrow ? (
                                <p className="text-sm text-muted-foreground">
                                    {eyebrow}
                                </p>
                            ) : null}
                            <h1
                                className="text-2xl font-semibold tracking-tight break-words text-foreground"
                                data-test="page-title"
                            >
                                {title}
                            </h1>
                            {description ? (
                                <p
                                    className="mt-1 max-w-3xl text-sm text-muted-foreground"
                                    data-test="page-description"
                                >
                                    {description}
                                </p>
                            ) : null}
                        </div>
                        {action ? (
                            <div
                                className="flex shrink-0 flex-wrap items-center gap-2"
                                data-test="page-action"
                            >
                                {action}
                            </div>
                        ) : null}
                    </div>
                </header>
                <ShellTabs tabs={tabs} label={tabsLabel ?? title} />
                <ShellTiles tiles={tiles} />
                <div className="flex min-w-0 flex-col gap-6">{children}</div>
            </div>
        </>
    );
}

export function ShellTabs({
    tabs,
    label,
}: {
    tabs: ShellTab[];
    label: string;
}) {
    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav
            aria-label={label}
            data-test="page-tabs"
            className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        >
            <ul className="flex w-max min-w-full gap-1 border-b">
                {tabs.map((tab) => (
                    <li key={tab.id} className="shrink-0">
                        <Link
                            href={tab.href}
                            prefetch
                            aria-current={tab.current ? 'page' : undefined}
                            data-test={`page-tab-${tab.id}`}
                            className={cn(
                                'inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tab.current
                                    ? '-mb-px border-primary font-semibold text-foreground'
                                    : 'border-transparent font-medium text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                            {tab.badge ? (
                                <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-md bg-primary px-1 text-xs font-medium text-primary-foreground tabular-nums">
                                    {tab.badge}
                                </span>
                            ) : null}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

const tone: Record<string, string> = {
    members: 'bg-brand/5 dark:bg-indigo-400/10',
    in_charge: 'bg-success/10',
    active_week: 'bg-electric/10',
    pending: 'bg-destructive/10',
    missing: 'bg-destructive/10',
    active: 'bg-success/10',
};

export function ShellTiles({ tiles }: { tiles: ShellTile[] }) {
    if (tiles.length === 0) {
        return null;
    }

    return (
        <ul
            className={cn(
                'grid grid-cols-2 gap-3',
                tiles.length >= 4 ? 'lg:grid-cols-4' : 'md:grid-cols-3',
            )}
            data-test="page-tiles"
        >
            {tiles.map((tile) => (
                <li
                    key={tile.key}
                    data-test="summary-card"
                    data-key={tile.key}
                    className={cn(
                        'rounded-xl border p-4 text-card-foreground',
                        tone[tile.key] ?? 'bg-card',
                    )}
                >
                    <p className="text-sm font-medium">{tile.label}</p>
                    <p className="mt-2 text-2xl font-semibold">{tile.figure}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {tile.caption}
                    </p>
                </li>
            ))}
        </ul>
    );
}

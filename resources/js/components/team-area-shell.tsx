import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageShell, { type ShellTile } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import type { PageLinks, TeamAreaHeader } from '@/types/team-area';

type Props = {
    area: TeamAreaHeader;
    action?: ReactNode;
    tiles?: ShellTile[];
    children: ReactNode;
};

export default function TeamAreaShell({
    area,
    action,
    tiles,
    children,
}: Props) {
    return (
        <PageShell
            title={area.title}
            headTitle={`${area.section}, ${area.title}`}
            description={area.description}
            tabs={area.tabs}
            tabsLabel={area.tabsLabel}
            action={action}
            tiles={tiles}
        >
            {children}
        </PageShell>
    );
}

export function TeamPager({
    pagination,
    previousLabel,
    nextLabel,
}: {
    pagination: PageLinks;
    previousLabel: string;
    nextLabel: string;
}) {
    if (
        pagination.showing === '' &&
        pagination.previousUrl === null &&
        pagination.nextUrl === null
    ) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            {pagination.showing ? (
                <p className="text-sm text-muted-foreground">
                    {pagination.showing}
                </p>
            ) : (
                <span />
            )}
            <div className="flex gap-2">
                {pagination.previousUrl ? (
                    <Button variant="outline" asChild>
                        <Link href={pagination.previousUrl}>
                            {previousLabel}
                        </Link>
                    </Button>
                ) : null}
                {pagination.nextUrl ? (
                    <Button variant="outline" asChild>
                        <Link href={pagination.nextUrl}>{nextLabel}</Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import TeamTabBar from '@/components/team-tab-bar';
import { Button } from '@/components/ui/button';
import type { PageLinks, TeamAreaHeader } from '@/types/team-area';

type Props = {
    area: TeamAreaHeader;
    children: ReactNode;
};

export default function TeamAreaShell({ area, children }: Props) {
    return (
        <div className="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-6 px-4 py-6">
            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                    {area.title}
                </h1>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {area.description}
                </p>
            </header>
            <TeamTabBar label={area.tabsLabel} tabs={area.tabs} />
            {children}
        </div>
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
    if (pagination.showing === '' && pagination.previousUrl === null && pagination.nextUrl === null) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            {pagination.showing ? (
                <p className="text-sm text-muted-foreground">{pagination.showing}</p>
            ) : (
                <span />
            )}
            <div className="flex gap-2">
                {pagination.previousUrl ? (
                    <Button variant="outline" asChild>
                        <Link href={pagination.previousUrl}>{previousLabel}</Link>
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

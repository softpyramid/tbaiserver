import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { TeamTab } from '@/types/team-area';

type Props = {
    label: string;
    tabs: TeamTab[];
};

export default function TeamTabBar({ label, tabs }: Props) {
    return (
        <nav aria-label={label} data-test="team-tabs" className="-mx-1 overflow-x-auto">
            <ul className="flex min-w-max gap-1 border-b">
                {tabs.map((tab) => (
                    <li key={tab.id} className="shrink-0">
                        <Link
                            href={tab.href}
                            prefetch
                            aria-current={tab.current ? 'page' : undefined}
                            data-test={`team-tab-${tab.id}`}
                            className={cn(
                                'inline-flex border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                'focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none',
                                tab.current
                                    ? '-mb-px border-brand font-semibold text-foreground'
                                    : 'border-transparent font-medium text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

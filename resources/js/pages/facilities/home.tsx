import { Link } from '@inertiajs/react';
import type { ShellTile } from '@/components/page-shell';
import TeamAreaShell from '@/components/team-area-shell';
import { Button } from '@/components/ui/button';
import type { TeamAreaHeader } from '@/types/team-area';

type Line = {
    label: string;
    value: string;
};

type Props = {
    area: TeamAreaHeader;
    tiles: ShellTile[];
    action: { href: string; label: string };
    dateLabel: string;
    lines: Line[];
    links: { href: string; label: string }[];
};

export default function FacilityHome({
    area,
    tiles,
    action,
    lines,
    links,
}: Props) {
    return (
        <TeamAreaShell
            area={area}
            tiles={tiles}
            action={
                <Button asChild data-test="open-register">
                    <Link href={action.href}>{action.label}</Link>
                </Button>
            }
        >
            <section
                className="rounded-xl border bg-card p-4 md:p-6"
                data-test="facility-lines"
            >
                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {lines.map((line) => (
                        <div key={line.label}>
                            <dt className="text-sm text-muted-foreground">
                                {line.label}
                            </dt>
                            <dd className="font-medium">{line.value}</dd>
                        </div>
                    ))}
                </dl>
            </section>
            {links.length > 0 ? (
                <div className="flex flex-wrap gap-4">
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="text-sm font-medium underline underline-offset-2"
                        >
                            {link.label}
                        </Link>
                    ))}
                </div>
            ) : null}
        </TeamAreaShell>
    );
}

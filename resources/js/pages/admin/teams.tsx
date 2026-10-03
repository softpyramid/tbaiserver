import { Head, Link, router } from '@inertiajs/react';
import { FormEvent } from 'react';
import TeamSummaryCards from '@/components/team-summary-cards';
import { TeamPager } from '@/components/team-area-shell';
import { Button } from '@/components/ui/button';
import type { PageLinks, SummaryCard } from '@/types/team-area';

type Option = {
    value: string;
    label: string;
};

type Column = {
    key: string;
    label: string;
};

type Row = {
    id: string;
    href: string | null;
    team: string;
    type: string;
    city: string;
    district: string;
    members: string;
    inCharge: string;
    lastActivity: string;
};

type Props = {
    title: string;
    description: string;
    cards: SummaryCard[];
    columns: Column[];
    filters: { district: string; sector: string };
    districts: Option[];
    sectors: Option[];
    rows: Row[];
    pagination: PageLinks;
    labels: {
        district: string;
        sector: string;
        any: string;
        apply: string;
        clear: string;
        empty: string;
        previous: string;
        next: string;
        open: string;
    };
};

export default function AdminTeams({
    title,
    description,
    cards,
    columns,
    filters,
    districts,
    sectors,
    rows,
    pagination,
    labels,
}: Props) {
    function TeamRowCard({ row, openLabel }: { row: Row; openLabel: string }) {
    const body = (
        <>
            <p className="font-medium">{row.team}</p>
            <p className="mt-1 text-sm text-muted-foreground">{row.type}</p>
            <p className="mt-1 text-sm text-muted-foreground">{row.district}</p>
            <p className="mt-1 text-sm">{row.inCharge}</p>
            <p className="mt-1 text-sm text-muted-foreground">{row.lastActivity}</p>
            {row.href ? <span className="sr-only">{openLabel}</span> : null}
        </>
    );

    if (!row.href) {
        return (
            <div data-test="admin-team-row" className="rounded-xl border bg-card p-4">
                {body}
            </div>
        );
    }

    return (
        <Link
            href={row.href}
            data-test="admin-team-row"
            className="block rounded-xl border bg-card p-4 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
        >
            {body}
        </Link>
    );
}

function onFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const query: Record<string, string> = {};

        data.forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        });

        router.get(window.location.pathname, query, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-6 px-4 py-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                </header>

                <TeamSummaryCards cards={cards} columns={3} />

                <form className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" onSubmit={onFilter}>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{labels.district}</span>
                        <select
                            name="district"
                            defaultValue={filters.district}
                            className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-base md:text-sm"
                        >
                            <option value="">{labels.any}</option>
                            {districts.map((district) => (
                                <option key={district.value} value={district.value}>
                                    {district.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{labels.sector}</span>
                        <select
                            name="sector"
                            defaultValue={filters.sector}
                            className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-base md:text-sm"
                        >
                            <option value="">{labels.any}</option>
                            {sectors.map((sector) => (
                                <option key={sector.value} value={sector.value}>
                                    {sector.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex items-end gap-2">
                        <Button type="submit">{labels.apply}</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.get(window.location.pathname, {}, { preserveScroll: true })}
                        >
                            {labels.clear}
                        </Button>
                    </div>
                </form>

                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{labels.empty}</p>
                ) : (
                    <>
                        <ul className="space-y-3 md:hidden">
                            {rows.map((row) => (
                                <li key={row.id}>
                                    <TeamRowCard row={row} openLabel={labels.open} />
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-x-auto rounded-xl border bg-card md:block">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        {columns.map((column) => (
                                            <th key={column.key} className="px-4 py-3 font-medium">
                                                {column.label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr key={row.id} className="border-b last:border-0">
                                            <td className="px-4 py-3">
                                                {row.href ? (
                                                    <Link
                                                        href={row.href}
                                                        data-test="admin-team-row"
                                                        className="font-medium text-foreground underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                                                    >
                                                        {row.team}
                                                        <span className="sr-only"> {labels.open}</span>
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium" data-test="admin-team-row">
                                                        {row.team}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">{row.type}</td>
                                            <td className="px-4 py-3">{row.city}</td>
                                            <td className="px-4 py-3">{row.district}</td>
                                            <td className="px-4 py-3">{row.members}</td>
                                            <td className="px-4 py-3">{row.inCharge}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{row.lastActivity}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                <TeamPager pagination={pagination} previousLabel={labels.previous} nextLabel={labels.next} />
            </div>
        </>
    );
}

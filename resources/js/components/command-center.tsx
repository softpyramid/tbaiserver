import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment, FormEvent, type ReactNode, useState } from 'react';
import PageShell from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type CommandOption = {
    value: string;
    label: string;
    province_id?: string;
};

export type CommandFilters = {
    from: string;
    to: string;
    province: string;
    district: string;
    sector: string;
    defaults: {
        from: string;
        to: string;
    };
    active: {
        from: boolean;
        to: boolean;
        province: boolean;
        district: boolean;
        sector: boolean;
    };
    notice: string | null;
    presets: Array<{
        key: string;
        label: string;
        from: string;
        to: string;
    }>;
};

export type CommandShow = {
    province: boolean;
    district: boolean;
    sector: boolean;
};

export type CommandLabels = {
    apply: string;
    clear: string;
    from: string;
    to: string;
    province: string;
    district: string;
    sector: string;
    empty: string;
    graph: string;
    table: string;
    cascade: string;
    monthly: string;
    sort: string;
    back: string;
    facilities: string;
    filters: string;
};

export type CommandCrumb = {
    title: string;
    href: string | null;
};

export type PageTab = {
    key: string;
    label: string;
    current: boolean;
    href?: string;
    onSelect?: () => void;
};

export type CommandScreenLink = {
    key: string;
    label: string;
    href: string;
    current: boolean;
};

export type CommandLink = {
    href: string;
    label: string;
};

export type CascadeKey =
    | 'suspects'
    | 'tested'
    | 'positive'
    | 'started_treatment';

export type CascadeBar = {
    key: CascadeKey;
    label: string;
    value: number;
};

export type MonthPoint = {
    key: string;
    label: string;
    suspects: number;
    tested: number;
    positive: number;
    started_treatment: number;
};

export type DistrictColumn = {
    key: string;
    label: string;
};

export type DistrictRow = {
    id: string;
    href: string;
    cells: Record<string, string>;
};

type FilterOptions = {
    provinces: CommandOption[];
    districts: CommandOption[];
    sectors: CommandOption[];
};

const tones = ['bg-primary', 'bg-primary/75', 'bg-primary/55', 'bg-primary/35'];

const filterKeys = ['from', 'to', 'province', 'district', 'sector'] as const;

export const commandPartial = [
    'scope',
    'screens',
    'filters',
    'options',
    'show',
    'labels',
    'links',
    'tat',
    'title',
    'totals',
    'kpis',
    'cascade',
    'months',
    'notification',
    'estimate',
    'hasReturns',
    'notCollected',
    'notice',
    'columns',
    'rows',
    'sort',
    'direction',
    'sectorTitle',
    'backUrl',
    'crumbs',
    'facilities',
    'boundaries',
    'attribution',
    'geometryUrl',
    'indicator',
    'indicatorLabel',
    'indicators',
    'districts',
    'hiddenMatches',
    'hiddenNote',
    'top',
    'selectedId',
    'trendHeading',
    'topHeading',
    'chooseDistrict',
    'listHeading',
    'indicatorField',
    'badge',
    'canEdit',
    'rules',
    'alerts',
    'metricOptions',
    'comparatorOptions',
    'alertLabels',
    'categories',
    'pending',
    'driverLabels',
    'storeUrl',
    'estimateStoreUrl',
    'yearOptions',
    'estimateYearOptions',
    'estimateAreas',
    'targetProvinces',
    'targets',
    'series',
    'estimates',
    'empty',
    'incidenceLabels',
];

function pathOf(url: string): string {
    return url.split('?')[0] ?? url;
}

export function queryFrom(
    filters: CommandFilters,
    extra: Record<string, string> = {},
): Record<string, string> {
    const query: Record<string, string> = {};

    for (const key of filterKeys) {
        if (filters[key] !== '') {
            query[key] = filters[key];
        }
    }

    return { ...query, ...extra };
}

export function CommandShell({
    title,
    eyebrow,
    scope,
    crumbs = [],
    tabs,
    filters,
    options,
    show,
    labels,
    links,
    preserved,
    banner,
    children,
}: {
    title: string;
    eyebrow?: string;
    scope: string;
    crumbs?: CommandCrumb[];
    tabs?: ReactNode;
    filters: CommandFilters;
    options: FilterOptions;
    show: CommandShow;
    labels: CommandLabels;
    links: CommandLink[];
    preserved?: Record<string, string>;
    banner?: ReactNode;
    children: ReactNode;
}) {
    const [filtersPending, setFiltersPending] = useState(false);
    const areaTabs = usePage().props.nav.commandTabs;
    const formKey = `${filters.from}|${filters.to}|${filters.province}|${filters.district}|${filters.sector}`;

    return (
        <PageShell
            title={title}
            tabs={areaTabs}
            tabsLabel={title}
            description={scope}
            eyebrow={eyebrow}
            crumbs={<CommandCrumbs crumbs={crumbs} />}
        >
            {banner}
            {tabs}
            <FilterBar
                key={formKey}
                filters={filters}
                options={options}
                show={show}
                labels={labels}
                preserved={preserved}
                onPending={setFiltersPending}
            />
            <div
                className={cn(
                    filtersPending && 'pointer-events-none opacity-60',
                )}
                aria-busy={filtersPending || undefined}
            >
                {children}
            </div>

            <div className="flex flex-wrap gap-4 text-sm">
                {links.map((link) => (
                    <Link
                        key={link.href}
                        href={link.href}
                        className="underline-offset-2 hover:underline"
                    >
                        {link.label}
                    </Link>
                ))}
            </div>
        </PageShell>
    );
}

export function SegmentedTabs({
    label,
    tabs,
}: {
    label: string;
    tabs: PageTab[];
}) {
    if (tabs.length === 0) {
        return null;
    }

    return (
        <div
            className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
            data-test="page-tabs"
        >
            <div
                role="tablist"
                aria-label={label}
                className="flex w-max min-w-full gap-1 border-b border-border"
            >
                {tabs.map((tab) => {
                    const className = cn(
                        'shrink-0 border-b-2 px-3 py-2 text-sm whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        tab.current
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    );

                    if (tab.href) {
                        return (
                            <Link
                                key={tab.key}
                                href={tab.href}
                                role="tab"
                                aria-selected={tab.current}
                                className={className}
                            >
                                {tab.label}
                            </Link>
                        );
                    }

                    return (
                        <button
                            key={tab.key}
                            type="button"
                            role="tab"
                            aria-selected={tab.current}
                            className={className}
                            onClick={tab.onSelect}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function CommandCrumbs({ crumbs }: { crumbs: CommandCrumb[] }) {
    if (crumbs.length === 0) {
        return null;
    }

    return (
        <Breadcrumb data-test="command-crumbs">
            <BreadcrumbList>
                {crumbs.map((crumb, index) => {
                    const isLast = index === crumbs.length - 1;

                    return (
                        <Fragment key={`${crumb.title}-${index}`}>
                            <BreadcrumbItem>
                                {isLast || crumb.href === null ? (
                                    <BreadcrumbPage>
                                        {crumb.title}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink asChild>
                                        <Link href={crumb.href}>
                                            {crumb.title}
                                        </Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                            {isLast ? null : <BreadcrumbSeparator />}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}

function FilterBar({
    filters,
    options,
    show,
    labels,
    preserved = {},
    onPending,
}: {
    filters: CommandFilters;
    options: FilterOptions;
    show: CommandShow;
    labels: CommandLabels;
    preserved?: Record<string, string>;
    onPending: (pending: boolean) => void;
}) {
    const page = usePage();
    const path = pathOf(page.url);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [province, setProvince] = useState(filters.province);
    const [district, setDistrict] = useState(filters.district);
    const [sector, setSector] = useState(filters.sector);
    const [pending, setPending] = useState(false);

    function visit(query: Record<string, string>) {
        router.get(
            path,
            { ...query, ...kept(preserved) },
            {
                preserveState: true,
                preserveScroll: true,
                only: commandPartial,
                onStart: () => {
                    setPending(true);
                    onPending(true);
                },
                onFinish: () => {
                    setPending(false);
                    onPending(false);
                },
            },
        );
    }

    function onFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visit(
            filled({
                from,
                to,
                province,
                district,
                sector,
            }),
        );
    }

    function onProvince(value: string) {
        setProvince(value);

        const chosen = options.districts.find(
            (option) => option.value === district,
        );

        if (
            value !== '' &&
            chosen?.province_id !== undefined &&
            chosen.province_id !== value
        ) {
            setDistrict('');
        }
    }

    const districts =
        province === ''
            ? options.districts
            : options.districts.filter(
                  (option) =>
                      option.value === '' || option.province_id === province,
              );
    const activeCount = activeFilterCount(filters, show);

    return (
        <details
            className="group rounded-lg border [&:not([open])>:not(summary)]:hidden"
            data-test="command-filters"
        >
            <summary className="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-sm font-medium marker:content-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                <ChevronRight
                    aria-hidden="true"
                    className="size-4 transition-transform duration-200 group-open:rotate-90 motion-reduce:transition-none"
                />
                <span>{labels.filters}</span>
                <span
                    className="text-muted-foreground tabular-nums"
                    data-test="filter-count"
                >
                    {activeCount}
                </span>
            </summary>
            <form
                className="grid grid-cols-1 gap-3 border-t p-3 sm:grid-cols-2"
                onSubmit={onFilter}
            >
                {filters.notice ? (
                    <p
                        className="text-sm text-muted-foreground sm:col-span-2"
                        data-test="filter-notice"
                    >
                        {filters.notice}
                    </p>
                ) : null}
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    {filters.presets.map((preset) => (
                        <Button
                            key={preset.key}
                            type="button"
                            variant="outline"
                            disabled={pending}
                            data-test={`period-${preset.key}`}
                            onClick={() => {
                                setFrom(preset.from);
                                setTo(preset.to);
                                visit(
                                    filled({
                                        from: preset.from,
                                        to: preset.to,
                                        province,
                                        district,
                                        sector,
                                    }),
                                );
                            }}
                        >
                            {preset.label}
                        </Button>
                    ))}
                </div>
                <DateField
                    name="from"
                    label={labels.from}
                    value={from}
                    onChange={setFrom}
                />
                <DateField
                    name="to"
                    label={labels.to}
                    value={to}
                    onChange={setTo}
                />
                <PlaceField
                    name="province"
                    label={labels.province}
                    value={province}
                    options={options.provinces}
                    shown={show.province}
                    onChange={onProvince}
                />
                <PlaceField
                    name="district"
                    label={labels.district}
                    value={district}
                    options={districts}
                    shown={show.district}
                    onChange={setDistrict}
                />
                <PlaceField
                    name="sector"
                    label={labels.sector}
                    value={sector}
                    options={options.sectors}
                    shown={show.sector}
                    onChange={setSector}
                />
                <div className="flex items-end gap-2">
                    <Button type="submit" disabled={pending}>
                        {labels.apply}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={pending}
                        onClick={() => {
                            setFrom(filters.defaults.from);
                            setTo(filters.defaults.to);
                            setProvince('');
                            setDistrict('');
                            setSector('');
                            visit({
                                from: filters.defaults.from,
                                to: filters.defaults.to,
                            });
                        }}
                    >
                        {labels.clear}
                    </Button>
                </div>
            </form>
        </details>
    );
}

function filled(values: Record<string, string>): Record<string, string> {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(values)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    return query;
}

function kept(preserved: Record<string, string>): Record<string, string> {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(preserved)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    return query;
}

function activeFilterCount(filters: CommandFilters, show: CommandShow): number {
    let count = 0;

    if (filters.active.from) {
        count += 1;
    }

    if (filters.active.to) {
        count += 1;
    }

    if (show.province && filters.active.province) {
        count += 1;
    }

    if (show.district && filters.active.district) {
        count += 1;
    }

    if (show.sector && filters.active.sector) {
        count += 1;
    }

    return count;
}

function DateField({
    name,
    label,
    value,
    onChange,
}: {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex min-w-0 flex-col gap-1 text-sm">
            <span>{label}</span>
            <Input
                name={name}
                type="date"
                value={value}
                aria-label={label}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}

function PlaceField({
    name,
    label,
    value,
    options,
    shown,
    onChange,
}: {
    name: string;
    label: string;
    value: string;
    options: CommandOption[];
    shown: boolean;
    onChange: (value: string) => void;
}) {
    if (!shown) {
        if (value === '') {
            return null;
        }

        return <input type="hidden" name={name} value={value} />;
    }

    return (
        <label className="flex min-w-0 flex-col gap-1 text-sm">
            <span>{label}</span>
            <select
                name={name}
                value={value}
                aria-label={label}
                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-base md:text-sm"
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

export function ViewToggle({
    mode,
    labels,
    onChange,
}: {
    mode: 'graph' | 'table';
    labels: CommandLabels;
    onChange: (mode: 'graph' | 'table') => void;
}) {
    return (
        <div
            className="flex flex-wrap gap-2"
            role="group"
            aria-label={`${labels.graph}, ${labels.table}`}
        >
            <Button
                type="button"
                variant={mode === 'graph' ? 'default' : 'outline'}
                data-test="view-graph"
                onClick={() => onChange('graph')}
            >
                {labels.graph}
            </Button>
            <Button
                type="button"
                variant={mode === 'table' ? 'default' : 'outline'}
                data-test="view-table"
                onClick={() => onChange('table')}
            >
                {labels.table}
            </Button>
        </div>
    );
}

export function CommandCharts({
    mode,
    cascade,
    months,
    labels,
}: {
    mode: 'graph' | 'table';
    cascade: CascadeBar[];
    months: MonthPoint[];
    labels: CommandLabels;
}) {
    if (mode === 'table') {
        return (
            <div className="flex min-w-0 flex-col gap-6">
                <section className="min-w-0">
                    <h2 className="mb-3 text-base font-medium">
                        {labels.cascade}
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <tbody>
                                {cascade.map((bar) => (
                                    <tr key={bar.key} className="border-b">
                                        <th className="px-2 py-2 text-left font-medium">
                                            {bar.label}
                                        </th>
                                        <td className="px-2 py-2 text-right tabular-nums">
                                            {bar.value}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <section className="min-w-0">
                    <h2 className="mb-3 text-base font-medium">
                        {labels.monthly}
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="px-2 py-2 font-medium">
                                        {labels.monthly}
                                    </th>
                                    {cascade.map((bar) => (
                                        <th
                                            key={bar.key}
                                            className="px-2 py-2 font-medium"
                                        >
                                            {bar.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {months.map((month) => (
                                    <tr key={month.key} className="border-b">
                                        <th className="px-2 py-2 text-left font-medium">
                                            {month.label}
                                        </th>
                                        {cascade.map((bar) => (
                                            <td
                                                key={bar.key}
                                                className="px-2 py-2 tabular-nums"
                                            >
                                                {monthValue(month, bar.key)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        );
    }

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <section className="min-w-0" data-test="cascade-chart">
                <h2 className="mb-3 text-base font-medium">{labels.cascade}</h2>
                <BarList items={cascade} />
            </section>
            <section className="min-w-0" data-test="monthly-chart">
                <h2 className="mb-3 text-base font-medium">{labels.monthly}</h2>
                <div className="flex flex-col gap-4">
                    {months.map((month) => (
                        <div key={month.key} className="min-w-0">
                            <h3 className="mb-2 text-sm font-medium">
                                {month.label}
                            </h3>
                            <BarList
                                items={cascade.map((bar) => ({
                                    key: bar.key,
                                    label: bar.label,
                                    value: monthValue(month, bar.key),
                                }))}
                            />
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}

function monthValue(month: MonthPoint, key: CascadeKey): number {
    switch (key) {
        case 'suspects':
            return month.suspects;
        case 'tested':
            return month.tested;
        case 'positive':
            return month.positive;
        case 'started_treatment':
            return month.started_treatment;
        default: {
            const unreachable: never = key;

            return unreachable;
        }
    }
}

function BarList({
    items,
}: {
    items: { key: string; label: string; value: number }[];
}) {
    const max = Math.max(1, ...items.map((item) => item.value));

    return (
        <div className="flex flex-col gap-3">
            {items.map((item, index) => (
                <div key={item.key} className="min-w-0">
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="min-w-0">{item.label}</span>
                        <span className="shrink-0 tabular-nums">
                            {item.value}
                        </span>
                    </div>
                    <div className="mt-1 h-2 rounded bg-muted">
                        <div
                            className={`h-2 rounded ${tones[index % tones.length]}`}
                            style={{
                                width: `${Math.min(100, (item.value / max) * 100)}%`,
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function useChartMode(): [
    'graph' | 'table',
    (mode: 'graph' | 'table') => void,
] {
    const [mode, setMode] = useState<'graph' | 'table'>('graph');

    return [mode, setMode];
}

export function DistrictTable({
    columns,
    rows,
    sort,
    direction,
    filters,
    labels,
}: {
    columns: DistrictColumn[];
    rows: DistrictRow[];
    sort: string;
    direction: 'asc' | 'desc';
    filters: CommandFilters;
    labels: CommandLabels;
}) {
    const page = usePage();
    const path = pathOf(page.url);

    function onSort(key: string) {
        const nextDirection =
            sort === key && direction === 'asc' ? 'desc' : 'asc';

        router.get(
            path,
            queryFrom(filters, { sort: key, direction: nextDirection }),
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return (
        <div className="min-w-0">
            <ul className="space-y-3 md:hidden">
                {rows.map((row) => (
                    <li key={row.id} className="rounded-lg border p-3">
                        {columns.map((column) => (
                            <div key={column.key} className="py-1">
                                <div className="text-sm text-muted-foreground">
                                    {column.label}
                                </div>
                                <div>
                                    {column.key === columns[0]?.key ? (
                                        <Link
                                            href={row.href}
                                            className="font-medium underline-offset-2 hover:underline"
                                        >
                                            {row.cells[column.key] ?? ''}
                                        </Link>
                                    ) : (
                                        row.cells[column.key]
                                    )}
                                </div>
                            </div>
                        ))}
                    </li>
                ))}
            </ul>

            <div
                className={
                    rows.length === 0
                        ? 'overflow-x-auto'
                        : 'hidden overflow-x-auto md:block'
                }
            >
                <table
                    className="w-full border-collapse text-sm"
                    data-test="district-table"
                >
                    <thead>
                        <tr className="border-b text-left">
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    className="px-2 py-2 font-medium"
                                >
                                    <button
                                        type="button"
                                        className="underline-offset-2 hover:underline"
                                        onClick={() => onSort(column.key)}
                                    >
                                        {column.label}
                                        {sort === column.key
                                            ? direction === 'asc'
                                                ? ' ↑'
                                                : ' ↓'
                                            : ''}
                                        <span className="sr-only">
                                            {labels.sort}
                                        </span>
                                    </button>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-2 py-4 text-sm text-muted-foreground"
                                >
                                    {labels.empty}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.id} className="border-b align-top">
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className="px-2 py-2"
                                        >
                                            {column.key === columns[0]?.key ? (
                                                <Link
                                                    href={row.href}
                                                    className="font-medium underline-offset-2 hover:underline"
                                                >
                                                    {row.cells[column.key] ??
                                                        ''}
                                                </Link>
                                            ) : (
                                                row.cells[column.key]
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

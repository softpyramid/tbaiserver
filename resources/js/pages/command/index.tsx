import { Link } from '@inertiajs/react';
import {
    type CascadeBar,
    CommandCharts,
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
    type MonthPoint,
    useChartMode,
    ViewToggle,
} from '@/components/command-center';

type Kpi = {
    key: string;
    label: string;
    value: string;
};

type UnavailableItem = {
    key: string;
    status: 'not_collected';
    label: string;
};

type Attention = {
    pendingRequests: number;
    pendingRequestsUrl: string;
    facilitiesWithoutReturn: number;
    facilitiesWithoutReturnUrl: string;
    allClear: boolean;
    labels: {
        heading: string;
        requests: string;
        facilities: string;
        allClear: string;
    };
};

type Props = {
    attention?: Attention | null;
    title: string;
    scope: string;
    screens: CommandScreenLink[];
    filters: CommandFilters;
    options: {
        provinces: CommandOption[];
        districts: CommandOption[];
        sectors: CommandOption[];
    };
    show: CommandShow;
    labels: CommandLabels;
    links: CommandLink[];
    kpis: Kpi[];
    cascade: CascadeBar[];
    months: MonthPoint[];
    hasReturns: boolean;
    notCollected: {
        heading: string;
        items: UnavailableItem[];
    };
};

function AttentionStrip({ attention }: { attention: Attention }) {
    const { labels } = attention;

    return (
        <section
            aria-label={labels.heading}
            data-test="needs-attention"
            className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm"
        >
            <h2 className="font-semibold">{labels.heading}</h2>
            {attention.allClear ? (
                <p
                    className="text-muted-foreground"
                    data-test="needs-attention-clear"
                >
                    {labels.allClear}
                </p>
            ) : (
                <ul className="flex flex-wrap gap-x-4 gap-y-1">
                    {attention.pendingRequests > 0 ? (
                        <li>
                            <Link
                                href={attention.pendingRequestsUrl}
                                className="font-medium underline underline-offset-2"
                                data-test="needs-attention-requests"
                            >
                                {labels.requests}
                            </Link>
                        </li>
                    ) : null}
                    {attention.facilitiesWithoutReturn > 0 ? (
                        <li>
                            <Link
                                href={attention.facilitiesWithoutReturnUrl}
                                className="font-medium underline underline-offset-2"
                                data-test="needs-attention-facilities"
                            >
                                {labels.facilities}
                            </Link>
                        </li>
                    ) : null}
                </ul>
            )}
        </section>
    );
}

export default function CommandIndex({
    attention,
    title,
    scope,
    filters,
    options,
    show,
    labels,
    links,
    kpis,
    cascade,
    months,
    hasReturns,
    notCollected,
}: Props) {
    const [mode, setMode] = useChartMode();

    return (
        <CommandShell
            title={title}
            scope={scope}
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
            banner={
                attention ? <AttentionStrip attention={attention} /> : undefined
            }
        >
            <ul
                className="grid grid-cols-2 gap-3 lg:grid-cols-3"
                data-test="command-kpis"
            >
                {kpis.map((kpi) => (
                    <li
                        key={kpi.key}
                        className="min-w-0 rounded-lg border p-3"
                        data-test={`kpi-${kpi.key}`}
                    >
                        <div className="text-sm text-muted-foreground">
                            {kpi.label}
                        </div>
                        <div className="mt-1 text-lg font-semibold break-words">
                            {kpi.value}
                        </div>
                    </li>
                ))}
            </ul>

            <section
                className="flex min-w-0 flex-col gap-3"
                data-test="not-collected"
            >
                <h2 className="text-sm font-medium text-muted-foreground">
                    {notCollected.heading}
                </h2>
                <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {notCollected.items.map((item) => (
                        <li
                            key={item.key}
                            className="min-w-0 rounded-lg border border-dashed p-3 text-muted-foreground"
                            data-test={`not-collected-${item.key}`}
                        >
                            <div className="text-sm">{item.label}</div>
                            <div className="mt-1 text-sm">
                                {notCollected.heading}
                            </div>
                        </li>
                    ))}
                </ul>
            </section>

            {hasReturns ? null : (
                <p className="text-sm text-muted-foreground">{labels.empty}</p>
            )}

            <ViewToggle mode={mode} labels={labels} onChange={setMode} />
            <CommandCharts
                mode={mode}
                cascade={cascade}
                months={months}
                labels={labels}
            />
        </CommandShell>
    );
}

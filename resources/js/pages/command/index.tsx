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
    change?: string | null;
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

type NotificationCard = {
    label: string;
    sub: string;
    period: string;
    period_label: string;
    cases: number | null;
    ytd_cases: number | null;
    population: number | null;
    population_year: number | null;
    source: string | null;
    rate: number | null;
    ytd_rate: number | null;
    status: 'no_notification_data' | 'population_missing' | null;
};

type EstimateCard = {
    label: string;
    sub: string | null;
    rate: number | null;
    lower: number | null;
    upper: number | null;
    year: number | null;
    area: string | null;
    scope: 'own' | 'national_reference' | null;
    source: string | null;
    method: string | null;
    reference_url: string | null;
    status: 'no_estimate' | null;
    message: string | null;
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
    notification?: NotificationCard | null;
    estimate?: EstimateCard | null;
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
    notification,
    estimate,
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
                        {kpi.change ? (
                            <div
                                className="mt-0.5 text-xs text-muted-foreground"
                                data-test={`kpi-${kpi.key}-change`}
                            >
                                {kpi.change}
                            </div>
                        ) : null}
                    </li>
                ))}
            </ul>

            {notification ? (
                <section
                    className="min-w-0 rounded-lg border p-4"
                    aria-label={notification.label}
                    data-test="notification-card"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                {notification.label}
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {notification.sub}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {notification.period_label}
                            </p>
                        </div>
                        <div className="text-right">
                            <div
                                className="text-2xl font-semibold"
                                data-test="notification-rate"
                            >
                                {notification.rate === null
                                    ? notification.status ===
                                      'no_notification_data'
                                        ? 'n/a'
                                        : 'n/a'
                                    : `${notification.rate.toFixed(1)}`}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                per 100,000 population
                            </div>
                            {notification.rate === null ? (
                                <div
                                    className="mt-0.5 text-xs"
                                    data-test="notification-status"
                                >
                                    {notification.status ===
                                    'no_notification_data'
                                        ? 'No notification data'
                                        : 'Population not set'}
                                </div>
                            ) : null}
                        </div>
                    </div>
                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                        <div data-test="notification-cases">
                            <dt className="text-muted-foreground">
                                Cases notified
                            </dt>
                            <dd className="font-medium">
                                {notification.cases === null
                                    ? 'n/a'
                                    : notification.cases.toLocaleString()}
                            </dd>
                        </div>
                        <div data-test="notification-ytd">
                            <dt className="text-muted-foreground">
                                Year to date
                            </dt>
                            <dd className="font-medium">
                                {notification.ytd_cases === null
                                    ? 'n/a'
                                    : notification.ytd_cases.toLocaleString()}
                            </dd>
                        </div>
                        <div data-test="notification-population">
                            <dt className="text-muted-foreground">
                                Population
                            </dt>
                            <dd className="font-medium">
                                {notification.population === null
                                    ? '-'
                                    : `${notification.population.toLocaleString()} · ${notification.population_year}`}
                            </dd>
                        </div>
                        <div data-test="notification-source">
                            <dt className="text-muted-foreground">Source</dt>
                            <dd className="font-medium">
                                {notification.source ?? '-'}
                            </dd>
                        </div>
                    </dl>
                </section>
            ) : null}

            {estimate ? (
                <section
                    className="min-w-0 rounded-lg border p-4"
                    aria-label={estimate.label}
                    data-test="estimate-card"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                {estimate.label}
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {estimate.sub}
                            </p>
                        </div>
                        <div className="text-right">
                            <div
                                className="text-2xl font-semibold"
                                data-test="estimate-rate"
                            >
                                {estimate.status === 'no_estimate' ||
                                estimate.rate === null
                                    ? 'n/a'
                                    : estimate.rate.toFixed(1)}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                per 100,000 population
                            </div>
                            {estimate.status === 'no_estimate' ? (
                                <div
                                    className="mt-0.5 text-xs"
                                    data-test="estimate-status"
                                >
                                    {estimate.message}
                                </div>
                            ) : null}
                        </div>
                    </div>
                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                        {estimate.lower !== null && estimate.upper !== null ? (
                            <div data-test="estimate-interval">
                                <dt className="text-muted-foreground">Interval</dt>
                                <dd className="font-medium">
                                    {`${estimate.lower.toFixed(1)} to ${estimate.upper.toFixed(1)}`}
                                </dd>
                            </div>
                        ) : null}
                        <div data-test="estimate-year">
                            <dt className="text-muted-foreground">Year</dt>
                            <dd className="font-medium">
                                {estimate.year === null ? '-' : estimate.year}
                            </dd>
                        </div>
                        <div data-test="estimate-source">
                            <dt className="text-muted-foreground">Source</dt>
                            <dd className="font-medium">
                                {estimate.source ?? '-'}
                            </dd>
                        </div>
                        <div data-test="estimate-method">
                            <dt className="text-muted-foreground">Method</dt>
                            <dd className="font-medium">
                                {estimate.method ?? '-'}
                            </dd>
                        </div>
                    </dl>
                </section>
            ) : null}

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

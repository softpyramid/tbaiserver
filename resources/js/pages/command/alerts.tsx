import { router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import {
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
    SegmentedTabs,
} from '@/components/command-center';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    SeverityBadge,
    severityBadgeClass,
    SeverityIcon,
    severityCardClass,
} from '@/components/severity-badge';
import {
    countBySeverity,
    matchesSeverity,
    sortBySeverity,
} from '@/lib/severity';
import { cn } from '@/lib/utils';

type Rule = {
    id: number;
    metric: string;
    metricLabel: string;
    comparator: string;
    comparatorLabel: string;
    threshold: string;
    severity: string;
    label: string;
    status: 'triggered' | 'not_triggered';
    statusLabel: string;
    updateUrl: string | null;
};

type AlertItem = {
    id: string;
    label: string;
    severity: string;
    metric: string;
    place: {
        type: 'district' | 'facility';
        id: string;
        name: string;
    };
    href: string;
};

type Props = {
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
    badge: string;
    canEdit: boolean;
    rules: Rule[];
    alerts: AlertItem[];
    metricOptions: CommandOption[];
    comparatorOptions: CommandOption[];
    alertLabels: {
        metric: string;
        comparator: string;
        threshold: string;
        severity: string;
        label: string;
        status: string;
        save: string;
        alerts: string;
        rules: string;
        severityAll: string;
        severityFilter: string;
    };
};

type AlertPanel = 'rules' | 'alerts';

export default function CommandAlerts({
    title,
    scope,
    filters,
    options,
    show,
    labels,
    links,
    badge,
    canEdit,
    rules,
    alerts,
    metricOptions,
    comparatorOptions,
    alertLabels,
}: Props) {
    const [panel, setPanel] = useState<AlertPanel>('rules');

    return (
        <CommandShell
            title={title}
            scope={scope}
            tabs={
                <SegmentedTabs
                    label={title}
                    tabs={[
                        {
                            key: 'rules',
                            label: alertLabels.rules,
                            current: panel === 'rules',
                            onSelect: () => setPanel('rules'),
                        },
                        {
                            key: 'alerts',
                            label: alertLabels.alerts,
                            current: panel === 'alerts',
                            onSelect: () => setPanel('alerts'),
                        },
                    ]}
                />
            }
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            <Badge data-test="thresholds-badge">{badge}</Badge>
            <AlertPanelBody
                panel={panel}
                canEdit={canEdit}
                rules={rules}
                alerts={alerts}
                metricOptions={metricOptions}
                comparatorOptions={comparatorOptions}
                alertLabels={alertLabels}
                empty={labels.empty}
            />
        </CommandShell>
    );
}

function AlertPanelBody({
    panel,
    canEdit,
    rules,
    alerts,
    metricOptions,
    comparatorOptions,
    alertLabels,
    empty,
}: {
    panel: AlertPanel;
    canEdit: boolean;
    rules: Rule[];
    alerts: AlertItem[];
    metricOptions: CommandOption[];
    comparatorOptions: CommandOption[];
    alertLabels: Props['alertLabels'];
    empty: string;
}) {
    switch (panel) {
        case 'rules':
            return (
                <section className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-base font-medium">
                        {alertLabels.rules}
                    </h2>
                    <ul className="flex flex-col gap-3" data-test="alert-rules">
                        {rules.map((rule) => (
                            <li
                                key={rule.id}
                                className="min-w-0 rounded-lg border p-3"
                                data-test={`alert-rule-${rule.id}`}
                            >
                                {canEdit && rule.updateUrl ? (
                                    <RuleForm
                                        rule={rule}
                                        metricOptions={metricOptions}
                                        comparatorOptions={comparatorOptions}
                                        alertLabels={alertLabels}
                                    />
                                ) : (
                                    <RuleText
                                        rule={rule}
                                        alertLabels={alertLabels}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            );
        case 'alerts':
            return (
                <AlertList
                    alerts={alerts}
                    alertLabels={alertLabels}
                    empty={empty}
                />
            );
        default: {
            const unreachable: never = panel;

            return unreachable;
        }
    }
}

function AlertList({
    alerts,
    alertLabels,
    empty,
}: {
    alerts: AlertItem[];
    alertLabels: Props['alertLabels'];
    empty: string;
}) {
    const [filter, setFilter] = useState<string | null>(null);
    const sorted = useMemo(() => sortBySeverity(alerts), [alerts]);
    const counts = useMemo(() => countBySeverity(alerts), [alerts]);
    const active =
        filter !== null && counts.some((c) => c.severity === filter)
            ? filter
            : null;
    const visible = sorted.filter((alert) => matchesSeverity(alert, active));

    return (
        <section className="flex min-w-0 flex-col gap-3">
            <h2 className="text-base font-medium">{alertLabels.alerts}</h2>
            {alerts.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <>
                    <div
                        role="group"
                        aria-label={alertLabels.severityFilter}
                        className="flex flex-wrap gap-2"
                        data-test="severity-summary"
                    >
                        <button
                            type="button"
                            aria-pressed={active === null}
                            onClick={() => setFilter(null)}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                active === null
                                    ? 'bg-foreground text-background'
                                    : 'bg-background text-foreground hover:bg-accent',
                            )}
                        >
                            {alertLabels.severityAll}
                            <span className="tabular-nums">
                                {alerts.length}
                            </span>
                        </button>
                        {counts.map((c) => (
                            <button
                                key={c.severity}
                                type="button"
                                aria-pressed={active === c.severity}
                                data-test={`severity-chip-${c.severity}`}
                                onClick={() =>
                                    setFilter(
                                        active === c.severity
                                            ? null
                                            : c.severity,
                                    )
                                }
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    active === c.severity
                                        ? cn(
                                              severityBadgeClass(c.severity),
                                              'border-transparent ring-2 ring-foreground/60 ring-offset-1 ring-offset-background',
                                          )
                                        : cn(
                                              severityBadgeClass(c.severity),
                                              'border-transparent opacity-90 hover:opacity-100',
                                          ),
                                )}
                            >
                                <SeverityIcon severity={c.severity} />
                                {c.label}
                                <span className="tabular-nums">{c.count}</span>
                            </button>
                        ))}
                    </div>
                    <ul className="flex flex-col gap-2" data-test="alert-list">
                        {visible.map((alert) => (
                            <li
                                key={alert.id}
                                className={cn(
                                    'flex min-w-0 flex-col items-start gap-1.5 rounded-lg border p-3',
                                    severityCardClass(alert.severity),
                                )}
                            >
                                <SeverityBadge severity={alert.severity} />
                                <a
                                    href={alert.href}
                                    className="font-medium break-words underline-offset-2 hover:underline"
                                >
                                    {alert.place.name}
                                </a>
                                <p className="text-sm break-words">
                                    {alert.label}
                                </p>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}

function RuleText({
    rule,
    alertLabels,
}: {
    rule: Rule;
    alertLabels: Props['alertLabels'];
}) {
    return (
        <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <Field label={alertLabels.label} value={rule.label} />
            <Field label={alertLabels.metric} value={rule.metricLabel} />
            <Field
                label={alertLabels.comparator}
                value={rule.comparatorLabel}
            />
            <Field label={alertLabels.threshold} value={rule.threshold} />
            <div className="min-w-0">
                <dt className="text-muted-foreground">
                    {alertLabels.severity}
                </dt>
                <dd className="pt-0.5">
                    <SeverityBadge severity={rule.severity} />
                </dd>
            </div>
            <Field label={alertLabels.status} value={rule.statusLabel} />
        </dl>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="break-words">{value}</dd>
        </div>
    );
}

function RuleForm({
    rule,
    metricOptions,
    comparatorOptions,
    alertLabels,
}: {
    rule: Rule;
    metricOptions: CommandOption[];
    comparatorOptions: CommandOption[];
    alertLabels: Props['alertLabels'];
}) {
    function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (rule.updateUrl === null) {
            return;
        }

        const data = new FormData(event.currentTarget);
        const payload: Record<string, string> = {};

        data.forEach((value, key) => {
            if (typeof value === 'string') {
                payload[key] = value;
            }
        });

        router.patch(rule.updateUrl, payload, { preserveScroll: true });
    }

    return (
        <form
            className="grid grid-cols-1 gap-3 sm:grid-cols-2"
            onSubmit={onSubmit}
        >
            <label className="flex min-w-0 flex-col gap-1 text-sm sm:col-span-2">
                <span>{alertLabels.label}</span>
                <input
                    name="label"
                    defaultValue={rule.label}
                    aria-label={alertLabels.label}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                />
            </label>
            <label className="flex min-w-0 flex-col gap-1 text-sm">
                <span>{alertLabels.metric}</span>
                <select
                    name="metric"
                    defaultValue={rule.metric}
                    aria-label={alertLabels.metric}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                >
                    {metricOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="flex min-w-0 flex-col gap-1 text-sm">
                <span>{alertLabels.comparator}</span>
                <select
                    name="comparator"
                    defaultValue={rule.comparator}
                    aria-label={alertLabels.comparator}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                >
                    {comparatorOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="flex min-w-0 flex-col gap-1 text-sm">
                <span>{alertLabels.threshold}</span>
                <input
                    name="threshold"
                    type="number"
                    step="any"
                    defaultValue={rule.threshold}
                    aria-label={alertLabels.threshold}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                />
            </label>
            <label className="flex min-w-0 flex-col gap-1 text-sm">
                <span className="flex items-center gap-2">
                    {alertLabels.severity}
                    <SeverityBadge severity={rule.severity} />
                </span>
                <input
                    name="severity"
                    defaultValue={rule.severity}
                    aria-label={alertLabels.severity}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                />
            </label>
            <div className="text-sm text-muted-foreground sm:col-span-2">
                {alertLabels.status}: {rule.statusLabel}
            </div>
            <div>
                <Button type="submit">{alertLabels.save}</Button>
            </div>
        </form>
    );
}

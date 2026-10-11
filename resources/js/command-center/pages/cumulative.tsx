import { useState } from 'react';
import { HBarChart } from '../charts/h-bar-chart';
import { LineAreaChart } from '../charts/line-area-chart';
import { RingGauge } from '../charts/ring-gauge';
import { fmt, formatValue, missingText } from '../format';
import { CumulativeSkeleton } from '../skeletons';
import type { Cumulative, Envelope } from '../types';
import { at, Chip, Kpi, Kpis, LoadState, Panel } from '../ui';
import { useApi } from '../use-api';

const BAR_COLORS: Record<string, string> = {
    suspects: '#3b6cf0',
    tested: '#22a6c9',
    positive: '#e5484d',
    negative: '#7c8db5',
    started_treatment: '#2fa36b',
    ai_assisted_tests: '#8a5cf0',
};

const RING_COLORS: Record<string, string> = {
    positivity: '#e5484d',
    treatment_success: '#2fa36b',
    lost_to_follow_up: '#ff9d1c',
};

const TILE_ICONS: Record<string, string> = {
    median_tat: '⏱',
    active_bmus: '🏥',
    negative: '⚪',
};

/** Positivity and rates compare against the previous equal-length period. */
const CHANGE_HELP = 'Percentage points vs the previous equal-length period.';
const FOOTNOTE =
    'Rates divide by the selected area\u2019s PBS Census 2023 population (KPI reference data \u00b7 Population). Positivity and rate changes are in percentage points versus the previous equal-length period.';

export function CumulativePage() {
    const { data, error, loading, reload } = useApi<Envelope<Cumulative>>(
        '/api/command/cumulative',
    );
    const [view, setView] = useState<'g' | 't'>('g');

    if (!data) {
        return (
            <LoadState
                loading={loading}
                error={error}
                onRetry={reload}
                skeleton={<CumulativeSkeleton />}
            />
        );
    }

    const scope = data.meta.scope_name ?? 'Sindh';
    const filters = (data.meta as { filters?: { district?: string | null } })
        .filters;
    const { cascade, performance, months, table, notification, estimate } =
        data.data;
    const estimateMissing =
        estimate == null ||
        estimate.status === 'no_estimate' ||
        estimate.rate == null;
    const estimateSub = estimateMissing
        ? null
        : estimate.sub || estimate.message || null;
    const estimateInterval =
        !estimateMissing &&
        estimate.lower != null &&
        estimate.upper != null;
    const decimal = (n: number) =>
        Number.isInteger(n) ? String(n) : n.toFixed(1);
    const bars = cascade
        .filter((c) => c.value !== null)
        .map((c) => ({
            label: c.label,
            value: c.value ?? 0,
            color: BAR_COLORS[c.key] ?? '#3b6cf0',
        }));
    const labels = months.map((m) => m.label);
    const run = (pick: (m: (typeof months)[number]) => number | null) =>
        months.map((m) => pick(m) ?? 0);
    const series = [
        {
            name: 'Tested',
            color: '#22a6c9',
            values: run((m) => m.tested),
            area: true,
        },
        {
            name: 'TB positive',
            color: '#e5484d',
            values: run((m) => m.positive),
        },
        {
            name: 'Started treatment',
            color: '#2fa36b',
            values: run((m) => m.started_treatment),
        },
    ];
    const icon = (key: string) => TILE_ICONS[key] ?? '';

    return (
        <>
            <div
                className="cc-abs"
                style={{ ...at(350, 74), display: 'flex', gap: 8 }}
                role="group"
                aria-label="View"
            >
                <Chip on={view === 'g'} onClick={() => setView('g')}>
                    📊 Graph
                </Chip>
                <Chip on={view === 't'} onClick={() => setView('t')}>
                    📋 Table
                </Chip>
            </div>

            {view === 't' ? (
                <table className="cc-tbl">
                    <thead>
                        <tr>
                            <th scope="col">Indicator</th>
                            <th scope="col">What it tells Govt</th>
                            <th scope="col">{scope} total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {table.map((row) => (
                            <tr key={row.key}>
                                <td>
                                    {row.icon} {row.label}
                                </td>
                                <td>{row.meaning}</td>
                                <td className="v">
                                    {formatValue(
                                        row.value,
                                        row.unit,
                                        row.status,
                                    )}
                                    {row.change ? (
                                        <span
                                            className="cc-change"
                                            title={CHANGE_HELP}
                                        >
                                            {' '}
                                            {row.change}
                                        </span>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div className="cc-abs cc-cum" style={at(350, 108, 1090, 970)}>
                    <Panel
                        id="cc-cascade"
                        title={`Diagnostic & treatment cascade: ${scope} total`}
                        sub="hover a bar for the exact figure"
                        style={at(0, 0, 560, 334)}
                    >
                        <div style={{ marginTop: 8 }}>
                            <HBarChart
                                items={bars}
                                w={530}
                                ml={130}
                                mr={58}
                                rh={38}
                                animate
                                label={`Diagnostic and treatment cascade for ${scope}`}
                            />
                        </div>
                    </Panel>

                    <Panel
                        id="cc-perf"
                        title="Programme performance"
                        sub={
                            filters?.district
                                ? 'rates for this district'
                                : 'rates across all districts'
                        }
                        style={at(575, 0, 515, 334)}
                    >
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-around',
                                marginTop: 10,
                            }}
                        >
                            {performance.rings.map((r) => (
                                <div
                                    key={r.key}
                                    style={{ textAlign: 'center' }}
                                >
                                    <RingGauge
                                        value={r.value}
                                        max={r.max}
                                        color={RING_COLORS[r.key] ?? '#3b6cf0'}
                                        label={r.label}
                                        status={r.status}
                                    />
                                    {r.change ? (
                                        <div
                                            className="cc-change"
                                            title={CHANGE_HELP}
                                        >
                                            {r.change}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                        <Kpis columns={3} style={{ marginTop: 14 }}>
                            {performance.tiles.map((t) => (
                                <Kpi
                                    key={t.key}
                                    label={`${icon(t.key)} ${t.label}`.trim()}
                                >
                                    {t.value === null
                                        ? missingText(t.status)
                                        : t.unit === 'days'
                                          ? `${t.value.toFixed(1)} days`
                                          : fmt(t.value)}
                                </Kpi>
                            ))}
                        </Kpis>
                    </Panel>

                    <Panel
                        id="cc-monthly"
                        title="Cumulative cases by month (JAN to OCT)"
                        sub="running total · hover a point for the value"
                        style={at(0, 346, 1090, 344)}
                    >
                        <div className="cc-legend">
                            {series.map((s) => (
                                <span key={s.name}>
                                    <i style={{ background: s.color }} />{' '}
                                    {s.name}
                                </span>
                            ))}
                        </div>
                        <LineAreaChart
                            labels={labels}
                            series={series}
                            w={1060}
                            h={262}
                            ml={56}
                            label="Cumulative cases by month, running total"
                        />
                    </Panel>

                    {estimate ? (
                        <Panel
                            id="cc-estimate"
                            title={
                                estimateMissing || estimate.year == null
                                    ? 'ESTIMATED TB INCIDENCE RATE'
                                    : `ESTIMATED TB INCIDENCE RATE · ${estimate.year}`
                            }
                            sub={estimateMissing ? undefined : estimateSub}
                            style={at(0, 700, 440, 260)}
                        >
                            {estimateMissing ? (
                                <p className="cc-state" style={{ marginTop: 12 }}>
                                    No approved estimate entered
                                </p>
                            ) : (
                                <>
                                    <div
                                        style={{
                                            textAlign: 'center',
                                            paddingTop: 18,
                                        }}
                                    >
                                        <div
                                            style={{
                                                fontSize: 38,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {decimal(estimate.rate as number)}
                                        </div>
                                        <div
                                            style={{ fontSize: 13, opacity: 0.75 }}
                                        >
                                            per 100,000 population
                                        </div>
                                        {estimateInterval ? (
                                            <div
                                                style={{
                                                    fontSize: 13,
                                                    opacity: 0.75,
                                                    marginTop: 6,
                                                }}
                                            >
                                                {decimal(estimate.lower as number)} to{' '}
                                                {decimal(estimate.upper as number)} per
                                                100,000
                                            </div>
                                        ) : null}
                                    </div>
                                    <dl
                                        style={{
                                            margin: '14px 0 0',
                                            display: 'grid',
                                            gap: '6px',
                                            fontSize: 13,
                                            color: '#10205a',
                                        }}
                                    >
                                        <div>
                                            <dt
                                                style={{
                                                    opacity: 0.75,
                                                    display: 'inline',
                                                }}
                                            >
                                                Source:{' '}
                                            </dt>
                                            <dd
                                                style={{
                                                    margin: 0,
                                                    display: 'inline',
                                                }}
                                            >
                                                {estimate.source}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                style={{
                                                    opacity: 0.75,
                                                    display: 'inline',
                                                }}
                                            >
                                                Method:{' '}
                                            </dt>
                                            <dd
                                                style={{
                                                    margin: 0,
                                                    display: 'inline',
                                                }}
                                            >
                                                {estimate.method}
                                            </dd>
                                        </div>
                                        {estimate.reference_url ? (
                                            <div>
                                                <a
                                                    href={estimate.reference_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    style={{ color: '#3b6cf0' }}
                                                >
                                                    Reference
                                                </a>
                                            </div>
                                        ) : null}
                                    </dl>
                                </>
                            )}
                        </Panel>
                    ) : null}

                    {notification ? (
                        <Panel
                            id="cc-notification"
                            title={`${notification.label} · ${notification.period_label}`}
                            sub={notification.sub}
                            style={
                                estimate
                                    ? at(455, 700, 635, 260)
                                    : at(0, 700, 1090, 260)
                            }
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 40,
                                    paddingTop: 18,
                                }}
                            >
                                <div
                                    style={{
                                        textAlign: 'center',
                                        minWidth: 170,
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 38,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {notification.rate === null
                                            ? 'n/a'
                                            : `${notification.rate.toFixed(1)}`}
                                    </div>
                                    <div
                                        style={{ fontSize: 13, opacity: 0.75 }}
                                    >
                                        per 100,000 population
                                    </div>
                                    {notification.rate === null ? (
                                        <div
                                            style={{
                                                fontSize: 12,
                                                opacity: 0.75,
                                                marginTop: 6,
                                                color:
                                                    notification.status ===
                                                    'no_notification_data'
                                                        ? undefined
                                                        : '#e5484d',
                                            }}
                                        >
                                            {notification.message ??
                                                (notification.status ===
                                                'no_notification_data'
                                                    ? 'No notification data'
                                                    : 'Population not set')}
                                        </div>
                                    ) : null}
                                </div>
                                <dl
                                    style={{
                                        flex: 1,
                                        margin: 0,
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: '10px 32px',
                                    }}
                                >
                                    <div>
                                        <dt
                                            style={{
                                                opacity: 0.75,
                                                fontSize: 13,
                                            }}
                                        >
                                            Cases notified ·{' '}
                                            {notification.period_label}
                                        </dt>
                                        <dd style={{ margin: 0, fontSize: 22 }}>
                                            {notification.cases === null
                                                ? 'n/a'
                                                : fmt(notification.cases)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            style={{
                                                opacity: 0.75,
                                                fontSize: 13,
                                            }}
                                        >
                                            Year to date
                                        </dt>
                                        <dd style={{ margin: 0, fontSize: 22 }}>
                                            {notification.ytd_cases === null
                                                ? 'n/a'
                                                : fmt(notification.ytd_cases)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            style={{
                                                opacity: 0.75,
                                                fontSize: 13,
                                            }}
                                        >
                                            Population
                                        </dt>
                                        <dd style={{ margin: 0, fontSize: 22 }}>
                                            {notification.population === null
                                                ? '-'
                                                : `${fmt(notification.population)} · ${notification.population_year}`}
                                        </dd>
                                    </div>
                                    {notification.source ? (
                                        <div>
                                            <dt
                                                style={{
                                                    opacity: 0.75,
                                                    fontSize: 13,
                                                }}
                                            >
                                                Source
                                            </dt>
                                            <dd
                                                style={{
                                                    margin: 0,
                                                    fontSize: 18,
                                                }}
                                            >
                                                {notification.source}
                                            </dd>
                                        </div>
                                    ) : null}
                                </dl>
                            </div>
                        </Panel>
                    ) : null}
                </div>
            )}
            <p className="cc-foot" style={{ fontSize: 12, opacity: 0.75 }}>
                {FOOTNOTE}
            </p>
        </>
    );
}

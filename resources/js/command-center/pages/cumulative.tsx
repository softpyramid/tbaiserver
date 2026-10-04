import { useState } from 'react';
import { HBarChart } from '../charts/h-bar-chart';
import { LineAreaChart } from '../charts/line-area-chart';
import { RingGauge } from '../charts/ring-gauge';
import { fmt, formatValue, missingText } from '../format';
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

export function CumulativePage() {
    const { data, error, loading, reload } = useApi<Envelope<Cumulative>>(
        '/api/command/cumulative',
    );
    const [view, setView] = useState<'g' | 't'>('g');

    if (!data) {
        return <LoadState loading={loading} error={error} onRetry={reload} />;
    }

    const scope = data.meta.scope_name ?? 'Sindh';
    const filters = (data.meta as { filters?: { district?: string | null } })
        .filters;
    const { cascade, performance, months, table } = data.data;
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
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div className="cc-abs cc-cum" style={at(350, 108, 1090, 690)}>
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
                                <RingGauge
                                    key={r.key}
                                    value={r.value}
                                    max={r.max}
                                    color={RING_COLORS[r.key] ?? '#3b6cf0'}
                                    label={r.label}
                                    status={r.status}
                                />
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
                </div>
            )}
        </>
    );
}

import { useState } from 'react';
import { LineAreaChart } from '../charts/line-area-chart';
import { IncidenceSkeleton } from '../skeletons';
import type { Envelope, Incidence, IncidenceMeta } from '../types';
import { at, Chip, Kpi, Kpis, LoadState, Panel } from '../ui';
import { useApi } from '../use-api';

const BLUE = '#4b7ff7';
const TARGET = '#2fa36b';
const NONE = 'n/a';

const signed = (n: number) => `${n > 0 ? '+' : ''}${n}`;
const HEAD = { fontSize: 22, letterSpacing: '.5px' } as const;
const NAVY = '#10205a';

const decimal = (n: number) =>
    Number.isInteger(n) ? String(n) : n.toFixed(1);
const fmtRate = (v: number) => `${decimal(v)} per 100,000`;

type YearPoint = {
    year: number;
    estimate: number | null;
    estimate_scope: 'own' | 'national_reference' | null;
    target: number | null;
};

/** Page 9: estimated TB incidence rate vs yearly planning targets. */
export function IncidencePage() {
    const state = useApi<Envelope<Incidence, IncidenceMeta>>(
        '/api/command/incidence',
    );
    const [sel, setSel] = useState<number | null>(null);
    const body = state.data?.data;

    if (!body) {
        return (
            <LoadState
                loading={state.loading}
                error={state.error}
                onRetry={state.reload}
                skeleton={<IncidenceSkeleton />}
            />
        );
    }

    const max = state.data?.meta.y_axis.max ?? 100;
    const estimate = body.estimate;
    const points = body.yearly.filter(
        (p) => p.estimate !== null,
    ) as YearPoint[];
    const hasAny =
        points.length > 0 || body.yearly.some((p) => p.target !== null);

    if (!hasAny && !estimate) {
        return (
            <Panel
                id="cc-inc-empty"
                title="ESTIMATED TB INCIDENCE RATE"
                titleStyle={HEAD}
                sub="No incidence estimate entered."
                style={at(340, 92, 540, 200)}
            >
                <p className="cc-state" style={{ marginTop: 6 }}>
                    Add an approved estimate and planning targets.
                </p>
            </Panel>
        );
    }

    const y = Math.min(
        sel ?? (points.findIndex((p) => p.year === body.defaults.year) >= 0
            ? points.findIndex((p) => p.year === body.defaults.year)
            : points.length - 1),
        points.length - 1,
    );
    const selected = points[y];
    const scopeSub =
        estimate === null
            ? null
            : estimate.note
              ? estimate.note
              : estimate.scope === 'national_reference'
                ? `(Pakistan national estimate, ${estimate.year})`
                : estimate.area_label
                  ? `(${estimate.area_label}, ${estimate.year})`
                  : null;
    const estValues = points.map((p) => p.estimate ?? 0);
    const tgtValues = points.map((p) => p.target ?? p.estimate ?? 0);
    const estTips = points.map((p, i) => (
        <>
            {p.year}
            <br />
            Estimated incidence: <b>{fmtRate(p.estimate ?? 0)}</b>
            <br />
            {p.estimate_scope === 'national_reference'
                ? 'Pakistan national estimate'
                : 'Local estimate'}
            <br />
            {i ? (
                <>
                    Change vs {points[i - 1].year}:{' '}
                    <b>{signed((p.estimate ?? 0) - (points[i - 1].estimate ?? 0))}</b>
                </>
            ) : (
                'Baseline year'
            )}
        </>
    ));
    const tgtTips = points.map((p) => (
        <>
            {p.year}
            <br />
            Target: <b>{p.target === null ? 'not set' : fmtRate(p.target)}</b>
        </>
    ));
    const prevEst =
        y > 0 ? (selected.estimate ?? 0) - (points[y - 1].estimate ?? 0) : null;

    return (
        <>
            <Panel
                id="cc-inc-est"
                title="ESTIMATED TB INCIDENCE RATE"
                titleStyle={HEAD}
                style={at(340, 92, 540, 696)}
            >
                {estimate === null ? (
                    <p className="cc-state" style={{ marginTop: 6 }}>
                        No approved estimate entered
                    </p>
                ) : (
                    <>
                        <div
                            style={{
                                fontSize: 72,
                                fontWeight: 700,
                                color: NAVY,
                                lineHeight: 1,
                                marginTop: 26,
                            }}
                        >
                            {decimal(estimate.rate)}
                        </div>
                        <div
                            className="sub"
                            style={{ marginTop: 8, fontSize: 16 }}
                        >
                            per 100,000 population
                        </div>
                        {estimate.lower !== null && estimate.upper !== null ? (
                            <div className="sub" style={{ marginTop: 12 }}>
                                {decimal(estimate.lower)} to{' '}
                                {decimal(estimate.upper)} per 100,000
                            </div>
                        ) : null}
                        {scopeSub ? (
                            <div
                                className="sub"
                                style={{ marginTop: 12, fontSize: 15 }}
                            >
                                {scopeSub}
                            </div>
                        ) : null}
                        {estimate.note && estimate.note !== scopeSub ? (
                            <p className="cc-state" style={{ marginTop: 16 }}>
                                {estimate.note}
                            </p>
                        ) : null}
                        <div
                            style={{
                                marginTop: 26,
                                borderTop: '1px solid rgba(16,32,90,.12)',
                                paddingTop: 12,
                                fontSize: 13,
                                lineHeight: '20px',
                                color: NAVY,
                            }}
                        >
                            <div>Source: {estimate.source}</div>
                            <div>Method: {estimate.method}</div>
                            {estimate.reference_url ? (
                                <div>
                                    Reference:{' '}
                                    <a
                                        href={estimate.reference_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        style={{ color: BLUE }}
                                    >
                                        open
                                    </a>
                                </div>
                            ) : null}
                        </div>
                    </>
                )}
            </Panel>

            {points.length > 0 ? (
                <Panel
                    id="cc-inc-y"
                    title="ESTIMATE VS TARGET: Yearly (2026 to 2030)"
                    titleStyle={HEAD}
                    style={at(895, 92, 545, 696)}
                >
                    <div className="cc-cr" style={{ margin: '6px 0' }}>
                        <span className="sub" style={{ lineHeight: '28px' }}>
                            <span style={{ color: BLUE }}>■</span> Estimated
                            incidence&nbsp;&nbsp;
                            <span style={{ color: TARGET }}>■</span> Target
                        </span>
                        <span className="sub" style={{ alignSelf: 'center' }}>
                            click a point to select a year
                        </span>
                    </div>
                    <LineAreaChart
                        labels={points.map((p) => String(p.year))}
                        series={[
                            {
                                name: 'Estimated incidence',
                                color: BLUE,
                                values: estValues,
                                area: true,
                                tips: estTips,
                            },
                            {
                                name: 'Target',
                                color: TARGET,
                                values: tgtValues,
                                tips: tgtTips,
                            },
                        ]}
                        w={510}
                        h={410}
                        max={max}
                        selected={y}
                        onSelect={setSel}
                        axis={decimal}
                        format={decimal}
                        label="Estimated TB incidence rate by year"
                    />
                    <Kpis columns={3} style={{ marginTop: 19 }}>
                        <Kpi label="Selected year">{selected.year}</Kpi>
                        <Kpi label="Estimated incidence">
                            {selected.estimate === null
                                ? NONE
                                : decimal(selected.estimate)}
                        </Kpi>
                        <Kpi label="Target">
                            {selected.target === null
                                ? NONE
                                : decimal(selected.target)}
                        </Kpi>
                        <Kpi label="Change vs previous year">
                            {prevEst === null ? NONE : signed(prevEst)}
                        </Kpi>
                        <Kpi label="Estimate scope">
                            {selected.estimate_scope === 'national_reference'
                                ? 'National'
                                : 'Local'}
                        </Kpi>
                        <Kpi label="Gap to target">
                            {selected.estimate === null ||
                            selected.target === null
                                ? NONE
                                : signed(selected.estimate - selected.target)}
                        </Kpi>
                    </Kpis>
                </Panel>
            ) : null}
        </>
    );
}
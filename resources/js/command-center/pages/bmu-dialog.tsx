import { useRef, useState } from 'react';
import { HBarChart } from '../charts/h-bar-chart';
import { LineAreaChart } from '../charts/line-area-chart';
import { fmt, missingText } from '../format';
import type { BmuDrilldown, Envelope } from '../types';
import { at, Chip, Kpi, Kpis, Panel } from '../ui';
import { useFocusTrap } from '../use-focus-trap';

type Metric = 'suspects' | 'tested' | 'positive';

const METRIC_LABEL: Record<Metric, string> = {
    suspects: 'Suspects',
    tested: 'Tested',
    positive: 'TB positive',
};
const METRIC_COLOR: Record<Metric, string> = {
    suspects: '#3b6cf0',
    tested: '#22a6c9',
    positive: '#e5484d',
};

const pct = (v: number | null, d: number) =>
    v === null ? missingText() : `${v.toFixed(d)}%`;

type Props = {
    sectorName: string;
    state: {
        data: Envelope<BmuDrilldown> | null;
        error: string | null;
        loading: boolean;
        reload: () => void;
    };
    onClose: () => void;
    onOpenMap: (cityId: number) => void;
};

/** BMU drill-down for one district (Public and Private Registered). */
export function BmuDialog({ sectorName, state, onClose, onOpenMap }: Props) {
    const box = useRef<HTMLDivElement>(null);
    const [sel, setSel] = useState(0);
    const [metric, setMetric] = useState<Metric>('positive');

    useFocusTrap(box, onClose);

    const body = state.data?.data;

    return (
        <div
            ref={box}
            className="cc-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cc-bmu-title"
        >
            <button
                type="button"
                className="x"
                aria-label="Close"
                onClick={onClose}
            >
                ×
            </button>

            {!body ? (
                <div
                    className="cc-state"
                    role={state.error ? 'alert' : 'status'}
                >
                    <h3
                        id="cc-bmu-title"
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            lineHeight: '34px',
                        }}
                    >
                        {sectorName} Registered · BMU-wise
                    </h3>
                    {state.error ? (
                        <p>
                            {state.error}{' '}
                            <button
                                type="button"
                                className="cc-chip"
                                onClick={state.reload}
                            >
                                Try again
                            </button>
                        </p>
                    ) : (
                        <p>Loading</p>
                    )}
                </div>
            ) : (
                <Drill
                    body={body}
                    sectorName={sectorName}
                    sel={sel}
                    setSel={setSel}
                    metric={metric}
                    setMetric={setMetric}
                    onOpenMap={onOpenMap}
                />
            )}
        </div>
    );
}

function Drill({
    body,
    sectorName,
    sel,
    setSel,
    metric,
    setMetric,
    onOpenMap,
}: {
    body: BmuDrilldown;
    sectorName: string;
    sel: number;
    setSel: (i: number) => void;
    metric: Metric;
    setMetric: (m: Metric) => void;
    onOpenMap: (cityId: number) => void;
}) {
    const { district, summary, bmus } = body;
    const b = bmus[Math.min(sel, Math.max(0, bmus.length - 1))];

    return (
        <>
            <h3
                id="cc-bmu-title"
                style={{
                    fontSize: 24,
                    fontWeight: 600,
                    lineHeight: '34px',
                    paddingRight: 44,
                }}
            >
                {district.name}: {sectorName} Registered · BMU-wise
            </h3>
            <div style={{ fontSize: 13, opacity: 0.8, marginBottom: 8 }}>
                {summary.active_bmus} active BMUs · Suspects{' '}
                {fmt(summary.suspects)} · Tested {fmt(summary.tested)} · TB
                positive {fmt(summary.positive)} · click a BMU to see its graph
                ·{' '}
                <button
                    type="button"
                    className="cc-lnk"
                    onClick={() => onOpenMap(district.city_id)}
                >
                    view district on map
                </button>
            </div>

            {!b ? (
                <p className="cc-state">No BMU returns in this period.</p>
            ) : (
                <>
                    <Panel
                        id="cc-bmu-compare"
                        title="BMU comparison"
                        style={at(18, 86, 520, 610)}
                    >
                        <div
                            className="cc-cr"
                            style={{ margin: '4px 0 8px' }}
                            role="group"
                            aria-label="Measure"
                        >
                            {(Object.keys(METRIC_LABEL) as Metric[]).map(
                                (k) => (
                                    <Chip
                                        key={k}
                                        on={k === metric}
                                        onClick={() => setMetric(k)}
                                    >
                                        {METRIC_LABEL[k]}
                                    </Chip>
                                ),
                            )}
                        </div>
                        <HBarChart
                            label={`BMU comparison in ${district.name}: ${METRIC_LABEL[metric]}`}
                            items={bmus.map((x) => ({
                                label: x.label,
                                value: x[metric],
                                text: `${x.label}: ${fmt(x[metric])}`,
                                tip: (
                                    <>
                                        <b>{x.label}</b>
                                        <br />
                                        Suspects: {fmt(x.suspects)}
                                        <br />
                                        Tested: {fmt(x.tested)}
                                        <br />
                                        TB positive: {fmt(x.positive)}
                                        <br />
                                        Positivity:{' '}
                                        {x.positivity === null
                                            ? missingText()
                                            : `${x.positivity.toFixed(1)}%`}
                                    </>
                                ),
                            }))}
                            w={490}
                            ml={70}
                            mr={56}
                            rh={Math.min(34, Math.floor(470 / bmus.length))}
                            color={METRIC_COLOR[metric]}
                            selected={sel}
                            onSelect={setSel}
                        />
                    </Panel>

                    <Panel
                        id="cc-bmu-month"
                        title={`${b.label}: monthly TB positive (JAN to OCT)`}
                        sub="hover a point for tested & positivity"
                        style={at(552, 86, 535, 610)}
                    >
                        <LineAreaChart
                            label={`${b.label}: monthly TB positive`}
                            labels={b.months.map((m) => m.label)}
                            series={[
                                {
                                    name: 'TB positive',
                                    color: '#e5484d',
                                    values: b.months.map(
                                        (m) => m.positive ?? 0,
                                    ),
                                    area: true,
                                    tips: b.months.map((m) => (
                                        <>
                                            {m.label} · {b.label}
                                            <br />
                                            TB positive:{' '}
                                            <b>{fmt(m.positive ?? 0)}</b>
                                            <br />
                                            Tested: {fmt(m.tested ?? 0)}
                                            <br />
                                            Positivity:{' '}
                                            {(m.tested
                                                ? ((m.positive ?? 0) /
                                                      m.tested) *
                                                  100
                                                : 0
                                            ).toFixed(1)}
                                            %
                                        </>
                                    )),
                                },
                            ]}
                            w={505}
                            h={290}
                            noSelect
                        />
                        <Kpis columns={4} style={{ marginTop: 14 }}>
                            <Kpi label="Suspects">{fmt(b.suspects)}</Kpi>
                            <Kpi label="Tested">{fmt(b.tested)}</Kpi>
                            <Kpi label="TB positive">{fmt(b.positive)}</Kpi>
                            <Kpi label="Positivity">{pct(b.positivity, 1)}</Kpi>
                            <Kpi label="Treatment success">
                                {pct(b.success_pct, 0)}
                            </Kpi>
                            <Kpi label="Lost to follow-up">
                                {pct(b.ltfu_pct, 1)}
                            </Kpi>
                            <Kpi label="Median TAT">
                                {b.tat_days === null
                                    ? missingText()
                                    : `${b.tat_days.toFixed(1)} d`}
                            </Kpi>
                            <Kpi label="Share of district cases">
                                {pct(b.share_of_district_positive, 0)}
                            </Kpi>
                        </Kpis>
                    </Panel>
                </>
            )}
        </>
    );
}

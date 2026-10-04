import { useState } from 'react';
import { HBarChart } from '../charts/h-bar-chart';
import { LineAreaChart } from '../charts/line-area-chart';
import type { Envelope, Incidence, IncidenceMeta } from '../types';
import { at, Chip, Kpi, Kpis, LoadState, Panel } from '../ui';
import { useApi } from '../use-api';

const BLUE = '#4b7ff7';
const NONE = 'n/a';

const signed = (n: number) => `${n > 0 ? '+' : ''}${n}`;
const HEAD = { fontSize: 22, letterSpacing: '.5px' } as const;

/** Page 9: incidence by month and by year, province level. */
export function IncidencePage() {
    const state = useApi<Envelope<Incidence, IncidenceMeta>>(
        '/api/command/incidence',
    );
    const [kind, setKind] = useState<'bar' | 'line' | null>(null);
    const [month, setMonth] = useState<number | null>(null);
    const [year, setYear] = useState(0);
    const body = state.data?.data;

    if (!body) {
        return (
            <LoadState
                loading={state.loading}
                error={state.error}
                onRetry={state.reload}
            />
        );
    }

    const max = state.data?.meta.y_axis.max ?? 100;
    const months = body.monthly.filter((m) => m.value !== null) as {
        month: string;
        label: string;
        value: number;
    }[];
    const years = body.yearly.filter((y) => y.value !== null) as {
        year: number;
        value: number;
    }[];

    if (months.length === 0 && years.length === 0) {
        return (
            <Panel
                id="cc-inc-empty"
                title="INCIDENCE"
                titleStyle={HEAD}
                sub="No incidence figures yet."
                style={at(340, 92, 540, 200)}
            >
                <p className="cc-state" style={{ marginTop: 6 }}>
                    Incidence appears here once the programme records it.
                </p>
            </Panel>
        );
    }

    const chart = kind ?? body.defaults.monthly_chart;
    const defaultMonth = months.findIndex(
        (x) => x.month === body.defaults.month,
    );
    const m = Math.min(
        month ?? (defaultMonth >= 0 ? defaultMonth : months.length - 1),
        months.length - 1,
    );
    const y = Math.min(year, years.length - 1);
    const mv = months.map((x) => x.value);
    const yv = years.map((x) => x.value);
    const mTips = months.map((x, i) => (
        <>
            {x.label}
            <br />
            Incidence: <b>{x.value}</b>
            <br />
            {i ? (
                <>
                    Change vs {months[i - 1].label}:{' '}
                    <b>{signed(x.value - months[i - 1].value)}</b>
                </>
            ) : (
                'Baseline month'
            )}
        </>
    ));
    const yTips = years.map((x, i) => (
        <>
            {x.year}
            <br />
            Incidence: <b>{x.value}</b>
            <br />
            {i ? (
                <>
                    Change vs {years[i - 1].year}:{' '}
                    <b>{signed(x.value - years[i - 1].value)}</b>
                </>
            ) : (
                'Baseline year'
            )}
        </>
    ));
    const highest = Math.max(...mv);
    const highestLabel = months[mv.indexOf(highest)]?.label;
    const round = (v: number) => String(Math.round(v));

    return (
        <>
            {months.length > 0 ? (
                <Panel
                    id="cc-inc-m"
                    title="INCIDENCE: Monthly"
                    titleStyle={HEAD}
                    style={at(340, 92, 540, 696)}
                >
                    <div className="cc-cr" style={{ margin: '6px 0' }}>
                        <Chip
                            on={chart === 'bar'}
                            onClick={() => setKind('bar')}
                        >
                            ▤ Bar
                        </Chip>
                        <Chip
                            on={chart === 'line'}
                            onClick={() => setKind('line')}
                        >
                            〰 Line
                        </Chip>
                        <span className="sub" style={{ alignSelf: 'center' }}>
                            click a {chart === 'bar' ? 'bar' : 'point'} to
                            select a month
                        </span>
                    </div>
                    {chart === 'bar' ? (
                        <HBarChart
                            items={months.map((x, i) => ({
                                label: x.label,
                                value: x.value,
                                tip: mTips[i],
                                text: `${x.label}: incidence ${x.value}`,
                            }))}
                            w={500}
                            ml={62}
                            mr={44}
                            rh={40}
                            max={max}
                            color={BLUE}
                            selected={m}
                            onSelect={setMonth}
                            animate
                            format={String}
                            axis={round}
                            label="Incidence by month"
                        />
                    ) : (
                        <LineAreaChart
                            labels={months.map((x) => x.label)}
                            series={[
                                {
                                    name: 'Incidence',
                                    color: BLUE,
                                    values: mv,
                                    area: true,
                                    tips: mTips,
                                },
                            ]}
                            w={500}
                            h={410}
                            max={max}
                            selected={m}
                            onSelect={setMonth}
                            axis={round}
                            format={String}
                            label="Incidence by month"
                        />
                    )}
                    <Kpis columns={3} style={{ marginTop: 19 }}>
                        <Kpi label="Selected month">{months[m].label}</Kpi>
                        <Kpi label="Incidence">{months[m].value}</Kpi>
                        <Kpi label="Change vs previous month">
                            {m ? signed(mv[m] - mv[m - 1]) : NONE}
                        </Kpi>
                        <Kpi label="Change since JAN">
                            {signed(mv[m] - mv[0])}
                        </Kpi>
                        <Kpi label={`Highest (${highestLabel})`}>{highest}</Kpi>
                        <Kpi
                            label={`Latest (${months[months.length - 1].label})`}
                        >
                            {mv[mv.length - 1]}
                        </Kpi>
                    </Kpis>
                </Panel>
            ) : null}

            {years.length > 0 ? (
                <Panel
                    id="cc-inc-y"
                    title="INCIDENCE: Yearly (2026 to 2030)"
                    titleStyle={HEAD}
                    style={at(895, 92, 545, 696)}
                >
                    <div
                        className="sub"
                        style={{ margin: '6px 0 4px', lineHeight: '28px' }}
                    >
                        click a point to select a year
                    </div>
                    <LineAreaChart
                        labels={years.map((x) => String(x.year))}
                        series={[
                            {
                                name: 'Incidence',
                                color: BLUE,
                                values: yv,
                                area: true,
                                tips: yTips,
                            },
                        ]}
                        w={510}
                        h={410}
                        max={max}
                        selected={y}
                        onSelect={setYear}
                        axis={round}
                        format={String}
                        label="Incidence by year"
                    />
                    <Kpis columns={3} style={{ marginTop: 19 }}>
                        <Kpi label="Selected year">{years[y].year}</Kpi>
                        <Kpi label="Incidence">{yv[y]}</Kpi>
                        <Kpi label="Change vs previous year">
                            {y ? signed(yv[y] - yv[y - 1]) : NONE}
                        </Kpi>
                        <Kpi label="Reduction since 2026">
                            {yv[0] > 0
                                ? `${(100 - (yv[y] / yv[0]) * 100).toFixed(0)}%`
                                : NONE}
                        </Kpi>
                        <Kpi label={String(years[0].year)}>{yv[0]}</Kpi>
                        <Kpi label={String(years[years.length - 1].year)}>
                            {yv[yv.length - 1]}
                        </Kpi>
                    </Kpis>
                </Panel>
            ) : null}
        </>
    );
}

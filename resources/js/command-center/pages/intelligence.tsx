import { HBarChart } from '../charts/h-bar-chart';
import { useCommand } from '../context';
import { cpt, fmt } from '../format';
import type { AlertLine, Envelope, Intelligence, MapSector } from '../types';
import { at, LoadState, Panel } from '../ui';
import { useApi } from '../use-api';

const SEVERITIES: {
    key: AlertLine['severity'];
    icon: string;
    label: string;
}[] = [
    { key: 'critical', icon: '🔴', label: 'Critical' },
    { key: 'attention', icon: '🟠', label: 'Attention' },
    { key: 'emerging', icon: '🟡', label: 'Emerging signal' },
    { key: 'improvement', icon: '🟢', label: 'Improvement' },
];

const COLOURS: Record<string, string> = {
    positivity: '#e5484d',
    tat: '#ff9d1c',
    treatment_gap: '#8a5cf0',
};

const INDICATOR_LABEL: Record<string, string> = {
    positivity: 'Positivity',
    median_tat: 'Median TAT',
    treatment_gap: 'Treatment gap',
};

/** Value text for the three charts, as the prototype prints them. */
const formatter = (indicator: string) => (v: number) =>
    indicator === 'positivity'
        ? `${v.toFixed(1)}%`
        : indicator === 'median_tat'
          ? `${v.toFixed(1)} d`
          : fmt(v);

const axisFormatter = (indicator: string) => (v: number) =>
    indicator === 'treatment_gap'
        ? cpt(v)
        : indicator === 'positivity'
          ? v.toFixed(0)
          : v.toFixed(1);

/**
 * The prototype's longest title wraps before "districts" and pushes its chart
 * down by a line; the break is kept so all three panels line up as drawn.
 */
function title(subject: string, scope: string, key: string) {
    if (key !== 'tat') {
        return `${subject}: ${scope}`;
    }

    const cut = scope.lastIndexOf(' ');

    return (
        <>
            {subject}: {scope.slice(0, cut)}
            <br />
            {scope.slice(cut + 1)}
        </>
    );
}

/** Page 2: AI Alert Center and the three top-eight charts. */
export function IntelligencePage() {
    const { openMap } = useCommand();
    const state = useApi<Envelope<Intelligence>>('/api/command/intelligence');
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

    const lines = body.alert_center.lines;
    const jump = (line: AlertLine) =>
        openMap(
            (line.map.sector || 'all') as MapSector,
            line.map.selected,
            line.map.indicator || undefined,
        );

    return (
        <>
            <section
                className="cc-txt"
                style={at(438, 92)}
                aria-labelledby="cc-alerts-h"
            >
                <h2 id="cc-alerts-h" className="cc-plain">
                    🚨 AI Alert Center
                </h2>
                <br />
                Instead of officials having to hunt through graphs:
                <br />
                {SEVERITIES.map((s) => {
                    const line = lines.find((l) => l.severity === s.key);

                    return (
                        <span key={s.key} className="cc-alert">
                            <span aria-hidden="true">{s.icon}</span> {s.label}
                            <br />
                            {line ? (
                                <button
                                    type="button"
                                    className="cc-lnk"
                                    onClick={() => jump(line)}
                                >
                                    {line.text}
                                </button>
                            ) : (
                                <span style={{ opacity: 0.7 }}>
                                    No current alert
                                </span>
                            )}
                            <br />
                        </span>
                    );
                })}
            </section>

            {body.top8.map((chart, j) => {
                const label =
                    INDICATOR_LABEL[chart.indicator] ?? chart.title_subject;
                const fmtValue = formatter(chart.indicator);

                return (
                    <Panel
                        key={chart.key}
                        id={`cc-top8-${chart.key}`}
                        title={title(
                            chart.title_subject,
                            chart.scope,
                            chart.key,
                        )}
                        sub="click a bar to open the district on the map"
                        style={at(340 + j * 369, 452, 355, 336)}
                    >
                        <HBarChart
                            items={chart.items.map((d) => ({
                                label: d.short_name,
                                value: d.value,
                                tip: (
                                    <>
                                        <b>{d.name}</b>
                                        <br />
                                        {label}: {fmtValue(d.value)}
                                    </>
                                ),
                                text: `${d.name}: ${label} ${fmtValue(d.value)}. Open on the map`,
                            }))}
                            w={327}
                            ml={104}
                            mr={52}
                            rh={30}
                            color={COLOURS[chart.key] ?? COLOURS.positivity}
                            animate
                            format={fmtValue}
                            axis={axisFormatter(chart.indicator)}
                            label={`${chart.title_subject}, ${chart.scope}`}
                            onSelect={(i) =>
                                openMap(
                                    'all',
                                    chart.items[i].city_id,
                                    chart.indicator,
                                )
                            }
                        />
                    </Panel>
                );
            })}
        </>
    );
}

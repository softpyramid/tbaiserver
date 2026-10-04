import type { KeyboardEvent, ReactNode } from 'react';
import { cpt, fmt, nice } from '../format';
import { useTip } from '../tooltip';
import { NAVY } from './h-bar-chart';

export type LineSeries = {
    name: string;
    color: string;
    values: number[];
    area?: boolean;
    tips?: ReactNode[];
};

type Props = {
    labels: string[];
    series: LineSeries[];
    w: number;
    h: number;
    ml?: number;
    max?: number;
    selected?: number | null;
    noSelect?: boolean;
    onSelect?: (index: number) => void;
    axis?: (v: number) => string;
    format?: (v: number) => string;
    label: string;
};

/** Line and area chart (prototype `lchart`), as a React SVG. */
export function LineAreaChart({
    labels,
    series,
    w,
    h,
    ml = 46,
    max,
    selected = null,
    noSelect = false,
    onSelect,
    axis = cpt,
    format = fmt,
    label,
}: Props) {
    const tip = useTip();
    const mb = 26;
    const mt = 12;
    const mr = 18;
    const ph = h - mt - mb;
    const pw = w - ml - mr;
    const mx = max || nice(Math.max(...series.flatMap((s) => s.values), 1));
    const X = (i: number) =>
        ml + (labels.length > 1 ? (pw * i) / (labels.length - 1) : pw / 2);
    const Y = (v: number) => mt + ph - (v / mx) * ph;

    const press = (i: number) => (e: KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onSelect?.(i);
        }
    };

    return (
        <>
            <svg
                className="cc-svg"
                width={w}
                height={h}
                viewBox={`0 0 ${w} ${h}`}
                role={onSelect ? 'group' : 'img'}
                aria-label={label}
            >
                {[0, 1, 2, 3, 4, 5].map((k) => {
                    const y = mt + ph - (ph * k) / 5;

                    return (
                        <g key={k} aria-hidden="true">
                            <line
                                x1={ml}
                                x2={w - mr}
                                y1={y}
                                y2={y}
                                stroke="rgba(16,32,90,.15)"
                            />
                            <text
                                x={ml - 6}
                                y={y + 4}
                                textAnchor="end"
                                fontSize={11}
                                fill={NAVY}
                                opacity={0.75}
                            >
                                {axis((mx * k) / 5)}
                            </text>
                        </g>
                    );
                })}
                {labels.map((l, i) => (
                    <text
                        key={`${l}-${i}`}
                        x={X(i)}
                        y={h - 8}
                        textAnchor="middle"
                        fontSize={11}
                        fill={NAVY}
                        opacity={0.85}
                        aria-hidden="true"
                    >
                        {l}
                    </text>
                ))}
                {series.map((sr) => {
                    const pts = sr.values
                        .map((v, i) => `${X(i)},${Y(v)}`)
                        .join(' ');

                    return (
                        <g key={sr.name}>
                            {sr.area ? (
                                <polygon
                                    points={`${X(0)},${Y(0)} ${pts} ${X(sr.values.length - 1)},${Y(0)}`}
                                    fill={sr.color}
                                    opacity={0.15}
                                />
                            ) : null}
                            <polyline
                                fill="none"
                                stroke={sr.color}
                                strokeWidth={3.5}
                                strokeLinejoin="round"
                                points={pts}
                            />
                            {sr.values.map((v, i) => {
                                const on = selected === i && !noSelect;
                                const content = sr.tips?.[i] ?? (
                                    <>
                                        {labels[i]}
                                        <br />
                                        {sr.name}: <b>{format(v)}</b>
                                    </>
                                );

                                return (
                                    <g
                                        key={`${sr.name}-${i}`}
                                        className={
                                            onSelect ? 'cc-hit' : undefined
                                        }
                                        style={{
                                            cursor: onSelect
                                                ? 'pointer'
                                                : 'default',
                                        }}
                                        {...(onSelect
                                            ? {
                                                  role: 'button',
                                                  tabIndex: 0,
                                                  'aria-label': `${labels[i]}: ${format(v)}`,
                                                  'aria-pressed': on,
                                                  onClick: () => onSelect(i),
                                                  onKeyDown: press(i),
                                              }
                                            : {})}
                                        {...tip(content)}
                                    >
                                        <circle
                                            cx={X(i)}
                                            cy={Y(v)}
                                            r={14}
                                            fill="transparent"
                                        />
                                        <circle
                                            cx={X(i)}
                                            cy={Y(v)}
                                            r={on ? 8 : 5.5}
                                            fill={on ? '#ff6a00' : sr.color}
                                            stroke="#fff"
                                            strokeWidth={1.5}
                                        />
                                    </g>
                                );
                            })}
                        </g>
                    );
                })}
            </svg>
            {onSelect ? null : (
                <table className="cc-sr">
                    <caption>{label}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Period</th>
                            {series.map((s) => (
                                <th key={s.name} scope="col">
                                    {s.name}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {labels.map((l, i) => (
                            <tr key={`${l}-${i}`}>
                                <th scope="row">{l}</th>
                                {series.map((s) => (
                                    <td key={s.name}>
                                        {format(s.values[i] ?? 0)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </>
    );
}

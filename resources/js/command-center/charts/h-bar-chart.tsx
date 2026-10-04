import type { KeyboardEvent, ReactNode } from 'react';
import { cpt, fmt } from '../format';
import { useTip } from '../tooltip';

export const NAVY = '#10205a';

export type HBarItem = {
    label: string;
    value: number;
    /** Plain text for screen readers (defaults to "label: value"). */
    text?: string;
    /** Tooltip content (defaults to "label: value"). */
    tip?: ReactNode;
    color?: string;
};

type Props = {
    items: HBarItem[];
    w: number;
    ml?: number;
    mr?: number;
    rh?: number;
    max?: number;
    color?: string;
    selected?: number | null;
    onSelect?: (index: number) => void;
    animate?: boolean;
    axis?: (v: number) => string;
    format?: (v: number) => string;
    label: string;
};

/** Horizontal bar chart (prototype `hbar`), as a React SVG. */
export function HBarChart({
    items,
    w,
    ml = 80,
    mr = 54,
    rh = 28,
    max,
    color,
    selected = null,
    onSelect,
    animate = false,
    axis = cpt,
    format = fmt,
    label,
}: Props) {
    const tip = useTip();
    const h = items.length * rh + 24;
    const mx = max || Math.max(...items.map((x) => x.value), 1) * 1.06;
    const pw = w - ml - mr;
    const reduced =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
                {[0, 1, 2, 3, 4].map((k) => {
                    const x = ml + (pw * k) / 4;

                    return (
                        <g key={k} aria-hidden="true">
                            <line
                                x1={x}
                                x2={x}
                                y1={0}
                                y2={h - 20}
                                stroke="rgba(16,32,90,.15)"
                            />
                            <text
                                x={x}
                                y={h - 6}
                                textAnchor="middle"
                                fontSize={11}
                                fill={NAVY}
                                opacity={0.7}
                            >
                                {axis((mx * k) / 4)}
                            </text>
                        </g>
                    );
                })}
                {items.map((d, i) => {
                    const y = i * rh + 3;
                    const bw = Math.max(2, (d.value / mx) * pw);
                    const on = selected === i;
                    const content = d.tip ?? (
                        <>
                            {d.label}: <b>{format(d.value)}</b>
                        </>
                    );

                    return (
                        <g
                            key={`${d.label}-${i}`}
                            className={onSelect ? 'cc-hit' : undefined}
                            style={{ cursor: onSelect ? 'pointer' : 'default' }}
                            {...(onSelect
                                ? {
                                      role: 'button',
                                      tabIndex: 0,
                                      'aria-label':
                                          d.text ??
                                          `${d.label}: ${format(d.value)}`,
                                      'aria-pressed': on,
                                      onClick: () => onSelect(i),
                                      onKeyDown: press(i),
                                  }
                                : {})}
                            {...tip(content)}
                        >
                            <rect
                                x={0}
                                y={y - 2}
                                width={w}
                                height={rh}
                                fill="transparent"
                            />
                            <text
                                x={ml - 8}
                                y={y + rh * 0.55}
                                textAnchor="end"
                                fontSize={12}
                                fill={NAVY}
                            >
                                {d.label}
                            </text>
                            <rect
                                x={ml}
                                y={y}
                                width={bw}
                                height={rh - 8}
                                rx={5}
                                fill={
                                    on
                                        ? '#ff6a00'
                                        : (d.color ?? color ?? '#3b6cf0')
                                }
                            >
                                {animate && !reduced ? (
                                    <animate
                                        attributeName="width"
                                        from="0"
                                        to={bw}
                                        dur=".6s"
                                    />
                                ) : null}
                            </rect>
                            <text
                                x={ml + bw + 6}
                                y={y + rh * 0.55}
                                fontSize={12}
                                fontWeight={600}
                                fill={NAVY}
                            >
                                {format(d.value)}
                            </text>
                        </g>
                    );
                })}
            </svg>
            {onSelect ? null : (
                <table className="cc-sr">
                    <caption>{label}</caption>
                    <tbody>
                        {items.map((d, i) => (
                            <tr key={`${d.label}-${i}`}>
                                <th scope="row">{d.label}</th>
                                <td>{d.text ?? format(d.value)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </>
    );
}

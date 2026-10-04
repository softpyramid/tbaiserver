import { missingText } from '../format';
import type { MetricStatus } from '../types';
import { useTip } from '../tooltip';
import { NAVY } from './h-bar-chart';

type Props = {
    value: number | null;
    max: number;
    color: string;
    label: string;
    status?: MetricStatus;
};

/** Ring gauge (prototype `ring`). A missing value draws the empty track and says why. */
export function RingGauge({ value, max, color, label, status }: Props) {
    const tip = useTip();
    const r = 40;
    const C = 2 * Math.PI * r;
    const p = value === null ? 0 : Math.min(1, value / max);
    const reduced =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const text = value === null ? missingText(status) : `${value.toFixed(1)}%`;

    return (
        <div
            className="cc-ring"
            role="group"
            aria-label={`${label}: ${text}`}
            tabIndex={0}
            {...tip(
                <>
                    {label}: <b>{text}</b>
                </>,
            )}
        >
            <svg
                className="cc-svg-inline"
                width={110}
                height={110}
                viewBox="0 0 110 110"
                aria-hidden="true"
            >
                <circle
                    cx={55}
                    cy={55}
                    r={r}
                    fill="none"
                    stroke="rgba(16,32,90,.15)"
                    strokeWidth={12}
                />
                {value === null ? null : (
                    <circle
                        cx={55}
                        cy={55}
                        r={r}
                        fill="none"
                        stroke={color}
                        strokeWidth={12}
                        strokeLinecap="round"
                        strokeDasharray={`${C * p} ${C}`}
                        transform="rotate(-90 55 55)"
                    >
                        {reduced ? null : (
                            <animate
                                attributeName="stroke-dasharray"
                                from={`0 ${C}`}
                                to={`${C * p} ${C}`}
                                dur=".8s"
                            />
                        )}
                    </circle>
                )}
                <text
                    x={55}
                    y={value === null ? 59 : 61}
                    textAnchor="middle"
                    fontSize={value === null ? 10 : 19}
                    fontWeight={600}
                    fill={NAVY}
                >
                    {text}
                </text>
            </svg>
            <div className="cc-ring-label">{label}</div>
        </div>
    );
}

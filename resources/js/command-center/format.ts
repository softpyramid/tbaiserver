import type { MetricStatus } from './types';

const LOCALE = 'en-US';

/** Whole number with thousands separators (prototype `fmt`). */
export const fmt = (x: number): string => Math.round(x).toLocaleString(LOCALE);

/** Compact axis label (prototype `cpt`). */
export const cpt = (v: number): string =>
    v >= 1e6
        ? `${(v / 1e6).toFixed(1)}M`
        : v >= 1000
          ? `${(v / 1000).toFixed(v >= 1e4 ? 0 : 1)}k`
          : String(Math.round(v));

/** Round an axis maximum up to 1, 2, 5 or 10 times a power of ten. */
export const nice = (x: number): number => {
    if (x <= 0) {
        return 1;
    }

    const p = 10 ** Math.floor(Math.log10(x));
    const f = x / p;

    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
};

/** What to print when the API has no number: never 0. */
export function missingText(status?: MetricStatus): string {
    return status === 'not_enough_returns'
        ? 'Not enough returns'
        : 'Not collected';
}

/** Value with its unit, exactly as the prototype prints it. */
export function formatValue(
    value: number | null,
    unit: string | null | undefined,
    status?: MetricStatus,
): string {
    if (value === null) {
        return missingText(status);
    }

    if (unit === '%') {
        return `${value.toFixed(1)}%`;
    }

    if (unit === 'days') {
        return `${value.toFixed(1)} days`;
    }

    if (unit === 'per_100k') {
        return `${value.toFixed(1)} per 100k`;
    }

    return fmt(value);
}

/**
 * Alert severity helpers for the Alerts pages.
 *
 * Severity is free text on each alert rule. The seeded values are
 * "critical", "attention" and "emerging". Each value maps to one of three
 * tones: critical (red), warning (amber) and info (blue). Unknown values
 * fall back to info so nothing is ever hidden.
 */
export type SeverityTone = 'critical' | 'warning' | 'info';

const TONES: Record<string, SeverityTone> = {
    critical: 'critical',
    severe: 'critical',
    high: 'critical',
    urgent: 'critical',
    warning: 'warning',
    attention: 'warning',
    medium: 'warning',
    moderate: 'warning',
    info: 'info',
    emerging: 'info',
    low: 'info',
    improvement: 'info',
};

const RANK: Record<SeverityTone, number> = {
    critical: 0,
    warning: 1,
    info: 2,
};

function key(severity: string): string {
    return severity.trim().toLowerCase();
}

export function severityTone(severity: string): SeverityTone {
    return TONES[key(severity)] ?? 'info';
}

export function severityRank(severity: string): number {
    return RANK[severityTone(severity)];
}

/** "attention" becomes "Attention". The label is always shown next to the colour. */
export function severityLabel(severity: string): string {
    const trimmed = severity.trim();

    return trimmed === ''
        ? trimmed
        : trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
}

/** Critical first, then warning, then info. Ties keep their existing order. */
export function sortBySeverity<T extends { severity: string }>(
    items: readonly T[],
): T[] {
    return items
        .map((item, index) => ({ item, index }))
        .sort(
            (a, b) =>
                severityRank(a.item.severity) - severityRank(b.item.severity) ||
                a.index - b.index,
        )
        .map(({ item }) => item);
}

export type SeverityCount = {
    severity: string;
    label: string;
    tone: SeverityTone;
    count: number;
};

/** One entry per severity value present, in severity order. */
export function countBySeverity(
    items: readonly { severity: string }[],
): SeverityCount[] {
    const counts = new Map<string, SeverityCount>();

    for (const item of items) {
        const k = key(item.severity);
        const current = counts.get(k);

        if (current) {
            current.count += 1;
        } else {
            counts.set(k, {
                severity: k,
                label: severityLabel(item.severity),
                tone: severityTone(item.severity),
                count: 1,
            });
        }
    }

    return sortBySeverity([...counts.values()]);
}

export function matchesSeverity(
    item: { severity: string },
    filter: string | null,
): boolean {
    return filter === null || key(item.severity) === filter;
}

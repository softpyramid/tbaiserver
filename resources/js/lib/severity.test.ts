import { describe, expect, test } from 'vite-plus/test';
import {
    countBySeverity,
    matchesSeverity,
    severityLabel,
    severityTone,
    sortBySeverity,
} from './severity';

describe('severityTone', () => {
    test('maps the seeded values and common names', () => {
        expect(severityTone('critical')).toBe('critical');
        expect(severityTone('attention')).toBe('warning');
        expect(severityTone('Warning')).toBe('warning');
        expect(severityTone('emerging')).toBe('info');
        expect(severityTone('info')).toBe('info');
    });

    test('unknown values fall back to info', () => {
        expect(severityTone('something else')).toBe('info');
        expect(severityTone('')).toBe('info');
    });
});

describe('severityLabel', () => {
    test('capitalises the stored value', () => {
        expect(severityLabel('critical')).toBe('Critical');
        expect(severityLabel(' attention ')).toBe('Attention');
        expect(severityLabel('')).toBe('');
    });
});

describe('sortBySeverity', () => {
    test('orders critical, warning, info and keeps the existing order within each', () => {
        const alerts = [
            { id: 'a', severity: 'emerging' },
            { id: 'b', severity: 'critical' },
            { id: 'c', severity: 'attention' },
            { id: 'd', severity: 'critical' },
            { id: 'e', severity: 'info' },
            { id: 'f', severity: 'warning' },
        ];

        expect(sortBySeverity(alerts).map((a) => a.id)).toEqual([
            'b',
            'd',
            'c',
            'f',
            'a',
            'e',
        ]);
        expect(alerts.map((a) => a.id)).toEqual(['a', 'b', 'c', 'd', 'e', 'f']);
    });
});

describe('countBySeverity', () => {
    test('counts each value present, in severity order', () => {
        const counts = countBySeverity([
            { severity: 'emerging' },
            { severity: 'critical' },
            { severity: 'Critical' },
            { severity: 'attention' },
        ]);

        expect(counts).toEqual([
            {
                severity: 'critical',
                label: 'Critical',
                tone: 'critical',
                count: 2,
            },
            {
                severity: 'attention',
                label: 'Attention',
                tone: 'warning',
                count: 1,
            },
            { severity: 'emerging', label: 'Emerging', tone: 'info', count: 1 },
        ]);
    });

    test('is empty with no alerts', () => {
        expect(countBySeverity([])).toEqual([]);
    });
});

describe('matchesSeverity', () => {
    test('null shows everything, a value filters case-insensitively', () => {
        expect(matchesSeverity({ severity: 'Critical' }, null)).toBe(true);
        expect(matchesSeverity({ severity: 'Critical' }, 'critical')).toBe(
            true,
        );
        expect(matchesSeverity({ severity: 'attention' }, 'critical')).toBe(
            false,
        );
    });
});

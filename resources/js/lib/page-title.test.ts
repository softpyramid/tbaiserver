import { readFileSync } from 'node:fs';
import { describe, expect, test } from 'vite-plus/test';
import { APP_NAME, pageTitle, SITE_TITLE } from './page-title';

const source = (path: string) =>
    readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

/** Shells that render <Head title> for the page that uses them. */
const titledShells: Record<string, string> = {
    PageShell: 'components/page-shell.tsx',
    CommandShell: 'components/command-center.tsx',
    TeamAreaShell: 'components/team-area-shell.tsx',
};

/** Where each role lands after signing in (RoleHome), plus deep links. */
const landingPages = [
    'pages/returns/today.tsx',
    'pages/suspect/index.tsx',
    'pages/suspect/new.tsx',
    'pages/resources/index.tsx',
    'pages/command/index.tsx',
    'pages/command/center.tsx',
    'pages/patients/index.tsx',
    'pages/notifications/index.tsx',
    'pages/facilities/home.tsx',
    'pages/settings/profile.tsx',
];

const setsTitle = (code: string) => /<Head\s[^>]*title=/.test(code);

describe('browser tab title', () => {
    test('formats a page title with the app name', () => {
        expect(pageTitle('Patients')).toBe(`Patients - ${APP_NAME}`);
    });

    test('falls back to the site title', () => {
        expect(pageTitle('')).toBe(SITE_TITLE);
        expect(pageTitle(SITE_TITLE)).toBe(SITE_TITLE);
    });

    test('the signed-in layout sets a default title', () => {
        const layout = source('layouts/app-layout.tsx');

        expect(layout).toMatch(/<Head title=\{SITE_TITLE\} \/>/);
        expect(layout.indexOf('<Head')).toBeLessThan(
            layout.indexOf('{children}'),
        );
    });

    test('each titled shell renders <Head title>', () => {
        for (const path of Object.values(titledShells)) {
            const code = source(path);

            expect(
                setsTitle(code) || /<PageShell\b/.test(code),
                path,
            ).toBe(true);
        }
    });

    test.each(landingPages)('%s sets its own title', (path) => {
        const code = source(path);
        const viaShell = Object.keys(titledShells).some((shell) =>
            new RegExp(`<${shell}\\b`).test(code),
        );

        expect(setsTitle(code) || viaShell).toBe(true);
    });
});

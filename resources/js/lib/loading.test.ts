import { describe, expect, test } from 'vite-plus/test';
import {
    idleVisit,
    LOAD_ERROR_TEXT,
    loadErrorText,
    type VisitEvent,
    visitPhase,
    visitReducer,
    type VisitState,
} from './loading';

const run = (events: VisitEvent[], from: VisitState = idleVisit) =>
    events.reduce(visitReducer, from);

describe('visit pending state', () => {
    test('starts idle', () => {
        expect(visitPhase(idleVisit)).toBe('idle');
    });

    test('is pending from start until finish', () => {
        expect(visitPhase(run(['start']))).toBe('pending');
        expect(visitPhase(run(['start', 'finish']))).toBe('idle');
    });

    test('a cancelled visit goes back to idle', () => {
        expect(visitPhase(run(['start', 'cancel']))).toBe('idle');
    });

    test('an HTTP or network failure stays failed after finish', () => {
        expect(visitPhase(run(['start', 'fail']))).toBe('failed');
        expect(visitPhase(run(['start', 'fail', 'finish']))).toBe('failed');
    });

    test('a visit still pending at the timeout reads as failed', () => {
        const timedOut = run(['start', 'timeout']);

        expect(timedOut.pending).toBe(true);
        expect(visitPhase(timedOut)).toBe('failed');
    });

    test('a late success after the timeout clears the error', () => {
        expect(visitPhase(run(['start', 'timeout', 'finish']))).toBe('idle');
    });

    test('retrying (a new start) clears an earlier failure', () => {
        expect(visitPhase(run(['start', 'fail', 'finish', 'start']))).toBe(
            'pending',
        );
        expect(
            visitPhase(run(['start', 'fail', 'finish', 'start', 'finish'])),
        ).toBe('idle');
    });

    test('a timeout or failure with nothing pending is ignored', () => {
        expect(run(['timeout'])).toEqual(idleVisit);
        expect(run(['fail'])).toEqual(idleVisit);
        expect(visitPhase(run(['start', 'finish', 'timeout']))).toBe('idle');
    });
});

describe('load error text', () => {
    const generic = 'The data could not be loaded.';

    test('the generic failure reads "Could not load."', () => {
        expect(loadErrorText(generic, generic)).toBe(LOAD_ERROR_TEXT);
        expect(LOAD_ERROR_TEXT).toBe('Could not load.');
    });

    test('specific messages (403, 404) pass through', () => {
        expect(
            loadErrorText('You do not have access to this page.', generic),
        ).toBe('You do not have access to this page.');
    });

    test('no error gives no text', () => {
        expect(loadErrorText(null, generic)).toBeNull();
    });
});

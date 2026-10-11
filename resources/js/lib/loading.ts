/** Shared copy and timing for loading and error states. */
export const LOADING_TEXT = 'Loading...';
export const LOADING_MAP_TEXT = 'Loading map data...';
export const LOAD_ERROR_TEXT = 'Could not load.';
export const RETRY_TEXT = 'Retry';

/** A request still pending after this long is shown as failed, with Retry. */
export const PENDING_TIMEOUT_MS = 20_000;

export type LoadPhase = 'idle' | 'pending' | 'failed';

export type VisitState = {
    pending: boolean;
    failed: boolean;
    timedOut: boolean;
};

export type VisitEvent = 'start' | 'fail' | 'timeout' | 'finish' | 'cancel';

export const idleVisit: VisitState = {
    pending: false,
    failed: false,
    timedOut: false,
};

/**
 * One Inertia visit: start, then optionally fail (HTTP or network error)
 * or time out, then finish or cancel. A new start clears any old failure,
 * so a later success removes the error overlay.
 */
export function visitReducer(state: VisitState, event: VisitEvent): VisitState {
    switch (event) {
        case 'start':
            return { pending: true, failed: false, timedOut: false };
        case 'fail':
            return state.pending ? { ...state, failed: true } : state;
        case 'timeout':
            return state.pending ? { ...state, timedOut: true } : state;
        case 'finish':
            return { pending: false, failed: state.failed, timedOut: false };
        case 'cancel':
            return idleVisit;
        default: {
            const unreachable: never = event;

            return unreachable;
        }
    }
}

export function visitPhase(state: VisitState): LoadPhase {
    if (state.failed || state.timedOut) {
        return 'failed';
    }

    return state.pending ? 'pending' : 'idle';
}

/** The message for a failed load; specific messages (403, 404) pass through. */
export function loadErrorText(
    error: string | null,
    generic: string,
): string | null {
    if (error === null) {
        return null;
    }

    return error === generic ? LOAD_ERROR_TEXT : error;
}

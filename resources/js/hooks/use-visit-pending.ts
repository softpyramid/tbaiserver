import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useReducer, useRef } from 'react';
import {
    idleVisit,
    type LoadPhase,
    PENDING_TIMEOUT_MS,
    visitPhase,
    visitReducer,
} from '@/lib/loading';

/**
 * Track the current Inertia GET visit (filters, sorting, partial reloads)
 * so pages can dim stale numbers while new ones load. Prefetches and
 * async visits are ignored. After `timeoutMs` a still-pending visit reads
 * as failed; `retry` repeats the last visit with the same options.
 */
export function useVisitPending(timeoutMs: number = PENDING_TIMEOUT_MS): {
    phase: LoadPhase;
    pending: boolean;
    retry: () => void;
} {
    const [state, dispatch] = useReducer(visitReducer, idleVisit);
    const current = useRef<PendingVisit | null>(null);
    const last = useRef<PendingVisit | null>(null);

    useEffect(() => {
        let timer: number | undefined;

        const stopTimer = () => {
            if (timer !== undefined) {
                window.clearTimeout(timer);
                timer = undefined;
            }
        };

        const offs = [
            router.on('start', (event) => {
                const visit = event.detail.visit;

                if (visit.method !== 'get' || visit.prefetch || visit.async) {
                    return;
                }

                stopTimer();
                current.current = visit;
                last.current = visit;
                dispatch('start');
                timer = window.setTimeout(() => dispatch('timeout'), timeoutMs);
            }),
            router.on('httpException', () => {
                if (current.current) {
                    dispatch('fail');
                }
            }),
            router.on('networkError', () => {
                if (current.current) {
                    dispatch('fail');
                }
            }),
            router.on('finish', (event) => {
                const visit = event.detail.visit;

                // Only the visit we are tracking; a cancelled earlier visit
                // can finish after its replacement has started.
                if (visit !== current.current) {
                    return;
                }

                stopTimer();
                current.current = null;
                dispatch(
                    visit.cancelled && !visit.interrupted ? 'cancel' : 'finish',
                );
            }),
        ];

        return () => {
            stopTimer();
            offs.forEach((off) => off());
        };
    }, [timeoutMs]);

    const retry = useCallback(() => {
        const visit = last.current;

        if (!visit) {
            router.reload();

            return;
        }

        router.visit(visit.url, {
            method: 'get',
            preserveState: visit.preserveState,
            preserveScroll: visit.preserveScroll,
            replace: visit.replace,
            only: visit.only,
            except: visit.except,
        });
    }, []);

    const phase = visitPhase(state);

    return { phase, pending: phase === 'pending', retry };
}

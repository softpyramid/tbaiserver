import { useCallback, useEffect, useState } from 'react';
import { LOAD_ERROR_TEXT, PENDING_TIMEOUT_MS } from '@/lib/loading';
import { ApiError, clearApiCache, GENERIC_LOAD_ERROR, getJson } from './api';

type State<T> = { data: T | null; error: string | null; loading: boolean };

/**
 * Load one API URL; `reload` clears the cache and tries again. A request
 * still pending after PENDING_TIMEOUT_MS reads as an error (so Retry shows),
 * but a late answer still replaces it.
 */
export function useApi<T>(url: string | null) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState<State<T> & { url: string | null }>({
        url: null,
        data: null,
        error: null,
        loading: true,
    });

    useEffect(() => {
        if (url === null) {
            return;
        }

        let live = true;
        const timer = window.setTimeout(() => {
            if (live) {
                setState({
                    url,
                    data: null,
                    error: LOAD_ERROR_TEXT,
                    loading: false,
                });
            }
        }, PENDING_TIMEOUT_MS);

        getJson<T>(url)
            .then(
                (data) =>
                    live &&
                    setState({ url, data, error: null, loading: false }),
            )
            .catch(
                (error: unknown) =>
                    live &&
                    setState({
                        url,
                        data: null,
                        error:
                            error instanceof ApiError
                                ? error.message
                                : GENERIC_LOAD_ERROR,
                        loading: false,
                    }),
            )
            .finally(() => window.clearTimeout(timer));

        return () => {
            live = false;
            window.clearTimeout(timer);
        };
    }, [url, attempt]);

    const reload = useCallback(() => {
        clearApiCache();
        setState((s) => ({ ...s, loading: true, error: null }));
        setAttempt((n) => n + 1);
    }, []);

    const current = state.url === url;

    return {
        data: current ? state.data : null,
        error: current ? state.error : null,
        loading: url !== null && (!current || state.loading),
        reload,
    };
}

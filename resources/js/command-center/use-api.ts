import { useCallback, useEffect, useState } from 'react';
import { ApiError, clearApiCache, getJson } from './api';

type State<T> = { data: T | null; error: string | null; loading: boolean };

/** Load one API URL; `reload` clears the cache and tries again. */
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
                                : 'The data could not be loaded.',
                        loading: false,
                    }),
            );

        return () => {
            live = false;
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

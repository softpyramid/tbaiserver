export class ApiError extends Error {
    status: number;

    constructor(status: number, message: string) {
        super(message);
        this.status = status;
    }
}

/** Message for any failure that is not a 401, 403 or 404. */
export const GENERIC_LOAD_ERROR = 'The data could not be loaded.';

const cache = new Map<string, Promise<unknown>>();

/** GET JSON from the command center API with the signed-in session. */
export function getJson<T>(url: string): Promise<T> {
    const hit = cache.get(url);

    if (hit) {
        return hit as Promise<T>;
    }

    const request = fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).then(async (response) => {
        if (response.status === 401) {
            window.location.assign('/login');
            throw new ApiError(401, 'Your session has ended.');
        }

        if (!response.ok) {
            throw new ApiError(
                response.status,
                response.status === 403
                    ? 'You do not have access to this page.'
                    : response.status === 404
                      ? 'This page is not available.'
                      : GENERIC_LOAD_ERROR,
            );
        }

        return (await response.json()) as T;
    });

    cache.set(url, request);
    request.catch(() => cache.delete(url));

    return request;
}

export function clearApiCache(): void {
    cache.clear();
}

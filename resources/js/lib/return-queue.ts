import { clearReturnDraft } from '@/lib/return-draft';

export type QueuedReturn = {
    teamSlug: string;
    reportedOn: string;
    url: string;
    payload: Record<string, string>;
};

const STORAGE_KEY = 'tbai.returnQueue';

const listeners = new Set<() => void>();

let syncing = false;

function emit(): void {
    listeners.forEach((listener) => listener());
}

function storage(): Storage | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function listQueuedReturns(): QueuedReturn[] {
    const raw = storage()?.getItem(STORAGE_KEY);

    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw) as QueuedReturn[];

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function writeQueue(items: QueuedReturn[]): void {
    try {
        storage()?.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // Ignore storage failures.
    }

    emit();
}

export function queueReturn(item: QueuedReturn): void {
    const rest = listQueuedReturns().filter(
        (row) =>
            !(
                row.teamSlug === item.teamSlug &&
                row.reportedOn === item.reportedOn
            ),
    );

    writeQueue([...rest, item]);
}

export function queuedReturn(
    teamSlug: string,
    reportedOn: string,
): QueuedReturn | null {
    return (
        listQueuedReturns().find(
            (row) => row.teamSlug === teamSlug && row.reportedOn === reportedOn,
        ) ?? null
    );
}

export function removeQueuedReturn(teamSlug: string, reportedOn: string): void {
    writeQueue(
        listQueuedReturns().filter(
            (row) =>
                !(row.teamSlug === teamSlug && row.reportedOn === reportedOn),
        ),
    );
}

export function subscribeReturnQueue(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function firstError(body: unknown): string {
    if (!body || typeof body !== 'object' || !('errors' in body)) {
        return '';
    }

    const errors = body.errors;

    if (!errors || typeof errors !== 'object') {
        return '';
    }

    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }

        if (typeof value === 'string') {
            return value;
        }
    }

    return '';
}

export async function syncQueuedReturns(): Promise<void> {
    if (syncing || typeof window === 'undefined' || !navigator.onLine) {
        return;
    }

    const items = listQueuedReturns();

    if (items.length === 0) {
        return;
    }

    syncing = true;

    try {
        for (const item of items) {
            if (!navigator.onLine) {
                return;
            }

            let response: Response;

            try {
                response = await fetch(item.url, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(item.payload),
                });
            } catch {
                return;
            }

            if (response.redirected && response.url.includes('/login')) {
                return;
            }

            if (response.ok) {
                removeQueuedReturn(item.teamSlug, item.reportedOn);
                clearReturnDraft(item.teamSlug, item.reportedOn);
                window.dispatchEvent(
                    new CustomEvent('tbai:returns-synced', { detail: item }),
                );
                continue;
            }

            if (response.status === 419) {
                return;
            }

            if (
                response.status === 422 ||
                (response.status >= 400 && response.status < 500)
            ) {
                const body = await response.json().catch(() => null);
                removeQueuedReturn(item.teamSlug, item.reportedOn);
                window.dispatchEvent(
                    new CustomEvent('tbai:return-sync-error', {
                        detail: {
                            teamSlug: item.teamSlug,
                            reportedOn: item.reportedOn,
                            message: firstError(body),
                        },
                    }),
                );
                continue;
            }

            return;
        }
    } finally {
        syncing = false;
        emit();
    }
}

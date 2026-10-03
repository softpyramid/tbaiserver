export type CountState = {
    suspects: string;
    tested: string;
    positive: string;
    negative: string;
    started_treatment: string;
    follow_up: string;
    median_tat_days: string;
};

export type ReturnDraft = {
    baseUpdatedAt: string | null;
    counts: CountState;
};

function storageKey(teamSlug: string, reportedOn: string): string {
    return `tbai.returnDraft.${teamSlug}.${reportedOn}`;
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

export function readReturnDraft(
    teamSlug: string,
    reportedOn: string,
): ReturnDraft | null {
    const raw = storage()?.getItem(storageKey(teamSlug, reportedOn));

    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as Partial<ReturnDraft>;

        if (!parsed.counts) {
            return null;
        }

        return {
            baseUpdatedAt: parsed.baseUpdatedAt ?? null,
            counts: parsed.counts,
        };
    } catch {
        return null;
    }
}

export function writeReturnDraft(
    teamSlug: string,
    reportedOn: string,
    draft: ReturnDraft,
): void {
    try {
        storage()?.setItem(
            storageKey(teamSlug, reportedOn),
            JSON.stringify(draft),
        );
    } catch {
        // A full or blocked store must not stop data entry.
    }
}

export function clearReturnDraft(teamSlug: string, reportedOn: string): void {
    try {
        storage()?.removeItem(storageKey(teamSlug, reportedOn));
    } catch {
        // Ignore storage failures.
    }
}

export function profileDraftKey(teamSlug: string | null): string {
    return `tbai.profileDraft.${teamSlug ?? 'new'}`;
}

export function screeningDraftKey(teamSlug: string): string {
    return `tbai.screeningDraft.${teamSlug}`;
}

export function readJson<T>(key: string): T | null {
    const raw = storage()?.getItem(key);

    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw) as T;
    } catch {
        return null;
    }
}

export function writeJson(key: string, value: unknown): void {
    try {
        storage()?.setItem(key, JSON.stringify(value));
    } catch {
        // Ignore storage failures.
    }
}

export function clearJson(key: string): void {
    try {
        storage()?.removeItem(key);
    } catch {
        // Ignore storage failures.
    }
}

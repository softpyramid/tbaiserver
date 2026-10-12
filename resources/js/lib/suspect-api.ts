/** Shapes and a small JSON client for the suspect screening API. */

export type SuspectQuestion = {
    key: string;
    section: string;
    section_label: string;
    prompt: string;
    help: string | null;
    options: string[];
    option_labels: Record<string, string>;
    why: string | null;
};

export type SuspectScores = {
    symptom: number;
    exposure: number;
    history: number;
    raw: number;
    score: number;
    is_probability: false;
};

export type SuspectProgressResponse = {
    assessment_id: string;
    status: 'in_progress' | 'completed';
    rule_set_version: string;
    rule_set_status: string;
    score_label: string;
    responses: { key: string; value: string }[];
    next_questions: SuspectQuestion[];
    progress: { answered: number; total: number };
    removed_answers: string[];
    scores: SuspectScores;
    unknown_items: string[];
    testing_criterion_met: boolean;
    alerts: string[];
};

export class SuspectApiError extends Error {
    status: number;

    constructor(status: number, message: string) {
        super(message);
        this.status = status;
    }
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function firstError(body: unknown): string | null {
    if (!body || typeof body !== 'object') {
        return null;
    }

    if ('errors' in body && body.errors && typeof body.errors === 'object') {
        for (const value of Object.values(body.errors)) {
            if (Array.isArray(value) && typeof value[0] === 'string') {
                return value[0];
            }
        }
    }

    if ('message' in body && typeof body.message === 'string') {
        return body.message;
    }

    return null;
}

/** POST JSON with the signed-in session and the CSRF token. */
export async function postSuspect<T>(
    url: string,
    payload: Record<string, unknown>,
    fallback: string,
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
    } catch {
        throw new SuspectApiError(0, fallback);
    }

    if (response.status === 401) {
        window.location.assign('/login');
        throw new SuspectApiError(401, fallback);
    }

    const body: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        throw new SuspectApiError(
            response.status,
            firstError(body) ?? fallback,
        );
    }

    return body as T;
}

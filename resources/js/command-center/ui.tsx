import type { CSSProperties, ReactNode } from 'react';
import { Spinner } from '@/components/spinner';
import { LOADING_TEXT, loadErrorText, RETRY_TEXT } from '@/lib/loading';
import { GENERIC_LOAD_ERROR } from './api';

/** Place a block on the 1456 x 819 stage (ignored on phones, where blocks stack). */
export const at = (
    left: number,
    top: number,
    width?: number,
    height?: number,
): CSSProperties => ({
    left,
    top,
    ...(width !== undefined ? { width } : {}),
    ...(height !== undefined ? { height } : {}),
});

export function Panel({
    title,
    sub,
    style,
    children,
    id,
    titleStyle,
}: {
    title: ReactNode;
    sub?: ReactNode;
    style: CSSProperties;
    children: ReactNode;
    id?: string;
    titleStyle?: CSSProperties;
}) {
    const heading = id ? `${id}-h` : undefined;

    return (
        <section className="cc-pn" style={style} aria-labelledby={heading}>
            <h4 id={heading} style={titleStyle}>
                {title}
            </h4>
            {sub ? <div className="sub">{sub}</div> : null}
            {children}
        </section>
    );
}

export function Chip({
    on,
    onClick,
    children,
    className = '',
    pressed,
}: {
    on?: boolean;
    onClick: () => void;
    children: ReactNode;
    className?: string;
    pressed?: boolean;
}) {
    return (
        <button
            type="button"
            className={`cc-chip${on ? ' on' : ''}${className ? ` ${className}` : ''}`}
            aria-pressed={pressed ?? on}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

export function Kpis({
    columns,
    children,
    style,
}: {
    columns: number;
    children: ReactNode;
    style?: CSSProperties;
}) {
    return (
        <div
            className="cc-kp"
            style={{ gridTemplateColumns: `repeat(${columns},1fr)`, ...style }}
        >
            {children}
        </div>
    );
}

export function Kpi({
    label,
    children,
}: {
    label: ReactNode;
    children: ReactNode;
}) {
    return (
        <div>
            {label}
            <b>{children}</b>
        </div>
    );
}

/** The message for a failed API call: "Could not load." or the 403/404 text. */
export const errorText = (error: string | null) =>
    loadErrorText(error, GENERIC_LOAD_ERROR);

/**
 * Loading or error state for a page whose API call has no data yet. Pass a
 * `skeleton` laid out like the loaded page to avoid a jump when data lands.
 */
export function LoadState({
    loading,
    error,
    onRetry,
    skeleton,
    status = LOADING_TEXT,
}: {
    loading: boolean;
    error: string | null;
    onRetry: () => void;
    skeleton?: ReactNode;
    status?: string;
}) {
    if (error) {
        return (
            <div
                className="cc-pn cc-state"
                style={at(350, 108, 520)}
                role="alert"
            >
                <p>{errorText(error)}</p>
                <p style={{ marginTop: 8 }}>
                    <button type="button" className="cc-chip" onClick={onRetry}>
                        {RETRY_TEXT}
                    </button>
                </p>
            </div>
        );
    }

    if (!loading) {
        return null;
    }

    if (skeleton) {
        return (
            <>
                {skeleton}
                <p className="cc-sr" role="status" aria-live="polite">
                    {status}
                </p>
            </>
        );
    }

    return (
        <div
            className="cc-pn cc-state"
            style={{
                ...at(350, 108, 320),
                display: 'flex',
                alignItems: 'center',
                gap: 10,
            }}
            role="status"
            aria-live="polite"
        >
            <Spinner size="sm" decorative />
            {LOADING_TEXT}
        </div>
    );
}

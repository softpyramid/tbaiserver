import type { CSSProperties, ReactNode } from 'react';

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
}: {
    title: ReactNode;
    sub?: ReactNode;
    style: CSSProperties;
    children: ReactNode;
    id?: string;
}) {
    const heading = id ? `${id}-h` : undefined;

    return (
        <section className="cc-pn" style={style} aria-labelledby={heading}>
            <h4 id={heading}>{title}</h4>
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

export function LoadState({
    loading,
    error,
    onRetry,
}: {
    loading: boolean;
    error: string | null;
    onRetry: () => void;
}) {
    if (error) {
        return (
            <div
                className="cc-pn cc-state"
                style={at(350, 108, 520)}
                role="alert"
            >
                <p>{error}</p>
                <p style={{ marginTop: 8 }}>
                    <button type="button" className="cc-chip" onClick={onRetry}>
                        Try again
                    </button>
                </p>
            </div>
        );
    }

    return loading ? (
        <div className="cc-pn cc-state" style={at(350, 108, 320)} role="status">
            Loading
        </div>
    ) : null;
}

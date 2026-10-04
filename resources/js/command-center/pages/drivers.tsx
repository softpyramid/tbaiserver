import { useState } from 'react';
import { useCommand } from '../context';
import type { Drivers, Envelope } from '../types';
import { at, LoadState } from '../ui';
import { useApi } from '../use-api';

const PINK = '#d6006e';

/** Page 6: the eleven drivers, six categories A to F, and the response for one. */
export function DriversPage() {
    const { openMap } = useCommand();
    const state = useApi<Envelope<Drivers>>('/api/command/drivers');
    const [picked, setPicked] = useState<string | null>(null);
    const body = state.data?.data;

    if (!body) {
        return (
            <LoadState
                loading={state.loading}
                error={state.error}
                onRetry={state.reload}
            />
        );
    }

    const category =
        body.categories.find(
            (c) => c.letter === (picked ?? body.default_category),
        ) ?? body.categories[0];

    if (!category) {
        return null;
    }

    const lit = new Set(category.signals);
    const plain = { listStyle: 'none', margin: 0, padding: 0 } as const;

    return (
        <>
            <section
                className="cc-txt"
                style={{ ...at(398, 149), lineHeight: '34px' }}
                aria-label="Driver variables"
            >
                <ul style={plain}>
                    {body.variables.map((v) => {
                        const on = lit.has(v.key);

                        return (
                            <li
                                key={v.key}
                                style={
                                    on
                                        ? { color: PINK, fontWeight: 600 }
                                        : v.available
                                          ? undefined
                                          : { opacity: 0.55 }
                                }
                            >
                                {v.label}
                                {on ? (
                                    <span className="cc-sr">
                                        {' '}
                                        (signal for this category)
                                    </span>
                                ) : null}
                                {v.available ? null : (
                                    <span className="cc-sr">
                                        {' '}
                                        (no data source yet)
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </section>

            {category.responses.length > 0 ? (
                <section
                    className="cc-txt"
                    style={at(398, 590)}
                    aria-label="Potential programme response"
                >
                    Potential programme response
                    <ul style={plain}>
                        {category.responses.map((line) => (
                            <li key={line}>
                                <span aria-hidden="true">→ </span>
                                {line}
                            </li>
                        ))}
                        {category.priority.length > 0 ? (
                            <li>
                                <span aria-hidden="true">→ </span>
                                priority:{' '}
                                {category.priority
                                    .map((p) => p.name)
                                    .join(', ')}{' '}
                                <button
                                    type="button"
                                    className="cc-lnk"
                                    onClick={() =>
                                        openMap('all', null, category.indicator)
                                    }
                                >
                                    · view on map
                                </button>
                            </li>
                        ) : null}
                    </ul>
                </section>
            ) : null}

            <nav
                className="cc-txt"
                style={{
                    ...at(1078, 354),
                    lineHeight: '70px',
                    pointerEvents: 'none',
                }}
                aria-label="Driver categories"
            >
                {body.categories.map((c) => {
                    const on = c.letter === category.letter;

                    return (
                        <div key={c.letter}>
                            <button
                                type="button"
                                className="cc-lnk"
                                aria-pressed={on}
                                style={
                                    on
                                        ? {
                                              color: PINK,
                                              fontWeight: 600,
                                              lineHeight: 1.25,
                                              pointerEvents: 'auto',
                                          }
                                        : {
                                              lineHeight: 1.25,
                                              pointerEvents: 'auto',
                                          }
                                }
                                onClick={() => setPicked(c.letter)}
                            >
                                {c.letter}. {c.label}
                            </button>
                        </div>
                    );
                })}
            </nav>

            <svg
                className="cc-abs cc-arrow"
                style={{ left: 0, top: 0, pointerEvents: 'none' }}
                width={1456}
                height={819}
                fill="none"
                stroke={PINK}
                strokeWidth={3.5}
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M731 592C760 520 850 478 920 512C975 540 950 612 900 608C860 604 872 545 935 528C985 514 1030 530 1064 555M1043 537L1065 556L1038 565" />
            </svg>
        </>
    );
}

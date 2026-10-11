import type { CSSProperties, ReactNode } from 'react';
import { Skeleton } from '@/components/skeleton';
import { Spinner } from '@/components/spinner';
import { LOADING_MAP_TEXT, RETRY_TEXT } from '@/lib/loading';
import { at, errorText } from './ui';

/*
 * Loading placeholders for each Command Center page. Every block sits at
 * the same `at(...)` stage position, with the same panel class, as the
 * loaded page, so nothing moves when the data arrives. All are aria-hidden;
 * LoadState adds the spoken "Loading..." status.
 */

const BAR_WIDTHS = [92, 70, 54, 38, 30, 22, 64, 46];

/** A bone in the stage ink colour (the default tint is too faint here). */
export function Bone({
    w,
    h = 12,
    style,
    round = false,
}: {
    w: number | string;
    h?: number;
    style?: CSSProperties;
    round?: boolean;
}) {
    return (
        <Skeleton
            className={
                round ? 'rounded-full bg-[#10205a]/15' : 'bg-[#10205a]/15'
            }
            style={{ width: w, height: h, ...style }}
        />
    );
}

function SkelPanel({
    style,
    children,
    sub = true,
}: {
    style: CSSProperties;
    children?: ReactNode;
    sub?: boolean;
}) {
    return (
        <section className="cc-pn" style={style} aria-hidden="true">
            <Bone w="55%" h={14} style={{ margin: '4px 0 8px' }} />
            {sub ? <Bone w="35%" h={9} style={{ marginBottom: 6 }} /> : null}
            {children}
        </section>
    );
}

/** Horizontal bars, label on the left, like HBarChart. */
function SkelBars({
    rows,
    rowHeight,
    labelWidth,
}: {
    rows: number;
    rowHeight: number;
    labelWidth: number;
}) {
    return (
        <div style={{ marginTop: 8 }}>
            {Array.from({ length: rows }, (_, i) => (
                <div
                    key={i}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        height: rowHeight,
                    }}
                >
                    <Bone w={labelWidth} h={10} />
                    <Bone
                        w={`${BAR_WIDTHS[i % BAR_WIDTHS.length] * 0.7}%`}
                        h={Math.max(10, rowHeight - 16)}
                    />
                </div>
            ))}
        </div>
    );
}

/** Vertical bars on a baseline, standing in for a line or area chart. */
function SkelChart({ height }: { height: number }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-end',
                gap: 14,
                height,
                marginTop: 10,
                padding: '0 8px 2px 56px',
                borderBottom: '1px solid rgba(16, 32, 90, 0.2)',
            }}
        >
            {BAR_WIDTHS.concat(BAR_WIDTHS.slice(0, 2)).map((pct, i) => (
                <Bone key={i} w="100%" h={(height - 16) * (pct / 100)} />
            ))}
        </div>
    );
}

function SkelLines({
    widths,
    gap = 12,
    h = 12,
}: {
    widths: (number | string)[];
    gap?: number;
    h?: number;
}) {
    return (
        <div style={{ display: 'grid', gap, marginTop: 10 }}>
            {widths.map((w, i) => (
                <Bone key={i} w={w} h={h} />
            ))}
        </div>
    );
}

function SkelChips({ count, style }: { count: number; style: CSSProperties }) {
    return (
        <div
            className="cc-abs"
            style={{ ...style, display: 'flex', gap: 8 }}
            aria-hidden="true"
        >
            {Array.from({ length: count }, (_, i) => (
                <Bone key={i} w={86} h={28} round />
            ))}
        </div>
    );
}

/** Page 1: cascade, rings and tiles, monthly chart, the two KPI cards. */
export function CumulativeSkeleton() {
    return (
        <>
            <SkelChips count={2} style={at(350, 74)} />
            <div className="cc-abs cc-cum" style={at(350, 108, 1090, 970)}>
                <SkelPanel style={at(0, 0, 560, 334)}>
                    <SkelBars rows={6} rowHeight={38} labelWidth={110} />
                </SkelPanel>

                <SkelPanel style={at(575, 0, 515, 334)}>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-around',
                            marginTop: 10,
                        }}
                    >
                        {[0, 1, 2].map((i) => (
                            <div
                                key={i}
                                style={{
                                    display: 'grid',
                                    justifyItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <Bone w={110} h={110} round />
                                <Bone w={80} h={10} />
                            </div>
                        ))}
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(3,1fr)',
                            gap: 8,
                            marginTop: 14,
                        }}
                    >
                        {[0, 1, 2].map((i) => (
                            <Bone key={i} w="100%" h={52} />
                        ))}
                    </div>
                </SkelPanel>

                <SkelPanel style={at(0, 346, 1090, 344)}>
                    <SkelChart height={262} />
                </SkelPanel>

                <SkelPanel style={at(0, 700, 440, 260)}>
                    <div style={{ display: 'grid', justifyItems: 'center' }}>
                        <Bone w={120} h={38} style={{ marginTop: 18 }} />
                        <Bone w={160} h={10} style={{ marginTop: 10 }} />
                    </div>
                    <SkelLines widths={['70%', '60%', '50%']} gap={10} />
                </SkelPanel>

                <SkelPanel style={at(455, 700, 635, 260)}>
                    <div style={{ display: 'flex', gap: 40, paddingTop: 18 }}>
                        <Bone w={140} h={38} />
                        <SkelLines widths={[260, 220, 240]} gap={10} />
                    </div>
                </SkelPanel>
            </div>
        </>
    );
}

/** Pages 3 to 5: the district table, header row and body rows. */
export function SectorSkeleton() {
    const columns = 7;

    return (
        <>
            <div
                className="cc-txt"
                style={{ ...at(340, 88), display: 'flex', height: 35 }}
                aria-hidden="true"
            >
                <Bone w={520} h={22} style={{ alignSelf: 'center' }} />
            </div>
            <div
                className="cc-dtw"
                style={at(340, 130, 1100, 655)}
                aria-hidden="true"
            >
                <SkelTable columns={columns} rows={16} />
            </div>
        </>
    );
}

function SkelTable({ columns, rows }: { columns: number; rows: number }) {
    const cells = Array.from({ length: columns }, (_, c) => c);

    return (
        <table className="cc-dt">
            <thead>
                <tr>
                    {cells.map((c) => (
                        <th key={c}>
                            <Bone w={c === 0 ? 110 : 60} h={11} />
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {Array.from({ length: rows }, (_, r) => (
                    <tr key={r}>
                        {cells.map((c) => (
                            <td key={c}>
                                <Bone w={c === 0 ? 120 : 48} h={11} />
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** BMU drill-down dialog: the comparison list and the BMU chart panel. */
export function BmuSkeleton() {
    return (
        <>
            <SkelPanel style={at(18, 86, 520, 610)}>
                <SkelChips count={3} style={{ position: 'static' }} />
                <SkelBars rows={12} rowHeight={40} labelWidth={150} />
            </SkelPanel>
            <SkelPanel style={at(552, 86, 535, 610)}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3,1fr)',
                        gap: 8,
                        marginTop: 8,
                    }}
                >
                    {[0, 1, 2].map((i) => (
                        <Bone key={i} w="100%" h={48} />
                    ))}
                </div>
                <SkelChart height={300} />
            </SkelPanel>
        </>
    );
}

/** Page 2: the alert text block and the three top-eight bar charts. */
export function IntelligenceSkeleton() {
    return (
        <>
            <div className="cc-txt" style={at(438, 92)} aria-hidden="true">
                <SkelLines
                    widths={[280, 0, 460, 220, 640, 220, 640, 220, 640]}
                    gap={14}
                    h={16}
                />
            </div>
            {[0, 1, 2].map((j) => (
                <SkelPanel key={j} style={at(340 + j * 369, 452, 355, 336)}>
                    <SkelBars rows={8} rowHeight={30} labelWidth={90} />
                </SkelPanel>
            ))}
        </>
    );
}

/** Page 6: the driver list, the response block and the category list. */
export function DriversSkeleton() {
    return (
        <>
            <div className="cc-txt" style={at(398, 149)} aria-hidden="true">
                <SkelLines
                    widths={[
                        300, 260, 340, 280, 240, 320, 270, 300, 250, 330, 280,
                    ]}
                    gap={18}
                    h={16}
                />
            </div>
            <div className="cc-txt" style={at(398, 590)} aria-hidden="true">
                <SkelLines widths={[320, 460, 420, 380]} gap={18} h={16} />
            </div>
            <div className="cc-txt" style={at(1078, 354)} aria-hidden="true">
                <SkelLines
                    widths={[260, 230, 280, 250, 270, 240]}
                    gap={50}
                    h={18}
                />
            </div>
        </>
    );
}

/** Page 9: the estimate panel and the estimate versus target chart. */
export function IncidenceSkeleton() {
    return (
        <>
            <SkelPanel style={at(340, 92, 540, 696)} sub={false}>
                <Bone w={180} h={48} style={{ marginTop: 18 }} />
                <Bone w={200} h={12} style={{ marginTop: 10 }} />
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2,1fr)',
                        gap: 8,
                        marginTop: 22,
                    }}
                >
                    {[0, 1, 2, 3].map((i) => (
                        <Bone key={i} w="100%" h={52} />
                    ))}
                </div>
                <SkelLines widths={['90%', '80%', '85%', '60%']} gap={12} />
            </SkelPanel>
            <SkelPanel style={at(895, 92, 545, 696)} sub={false}>
                <Bone w="60%" h={10} style={{ margin: '12px 0' }} />
                <SkelChart height={360} />
                <SkelLines widths={['80%', '70%']} gap={12} />
            </SkelPanel>
        </>
    );
}

/** Page 8: the patient chips and the four result cards. */
export function AiTestsSkeleton() {
    const card = (left: number, top: number, body: ReactNode) => (
        <section className="cc-ac" style={at(left, top)} aria-hidden="true">
            <Bone w={220} h={22} style={{ margin: '4px 0 14px' }} />
            {body}
        </section>
    );

    return (
        <>
            <SkelChips count={5} style={at(350, 96)} />
            {card(
                350,
                150,
                <SkelLines widths={['80%', '60%', 0, '40%', '70%']} />,
            )}
            {card(
                910,
                150,
                <>
                    <Bone w={150} h={60} />
                    <Bone w={200} h={14} style={{ marginTop: 10 }} />
                    <Bone w="100%" h={14} style={{ marginTop: 12 }} round />
                </>,
            )}
            {card(350, 470, <SkelLines widths={['85%', '65%', '75%']} />)}
            {card(910, 470, <SkelLines widths={['75%', '80%', '55%']} />)}
        </>
    );
}

/** Where the map is drawn on the stage; status messages centre on it. */
const MAP_AREA = at(420, 112, 510, 528);

/**
 * Centred over the map: a spinner with "Loading map data...", or the error
 * with Retry. Only the error box takes pointer events.
 */
export function MapStatus({
    error,
    onRetry,
}: {
    error: string | null;
    onRetry: () => void;
}) {
    return (
        <div
            className="cc-abs"
            style={{
                ...MAP_AREA,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                pointerEvents: 'none',
            }}
            role={error ? 'alert' : 'status'}
            aria-live={error ? undefined : 'polite'}
            data-test="cc-map-status"
        >
            <div
                className="cc-state"
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    padding: '10px 16px',
                    borderRadius: 12,
                    background: 'rgba(255, 255, 255, 0.85)',
                    border: '1px solid rgba(16, 32, 90, 0.25)',
                    pointerEvents: error ? 'auto' : 'none',
                }}
            >
                {error ? (
                    <>
                        {errorText(error)}
                        <button
                            type="button"
                            className="cc-chip"
                            onClick={onRetry}
                        >
                            {RETRY_TEXT}
                        </button>
                    </>
                ) : (
                    <>
                        <Spinner size="sm" decorative />
                        {LOADING_MAP_TEXT}
                    </>
                )}
            </div>
        </div>
    );
}

/** Top 5 rows while values load. */
export function TopRowsSkeleton() {
    return (
        <div aria-hidden="true">
            {[0, 1, 2, 3, 4].map((i) => (
                <div
                    key={i}
                    className="cc-tr"
                    style={{ alignItems: 'center', height: 24 }}
                >
                    <Bone w={140 - i * 12} h={10} />
                    <Bone w={40} h={10} />
                </div>
            ))}
        </div>
    );
}

/** Page 7 before any map data: the empty map with a spinner, and the side panel. */
export function MapSkeleton() {
    return (
        <>
            <div
                className="cc-abs cc-state"
                style={{
                    ...MAP_AREA,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 10,
                }}
                aria-hidden="true"
            >
                <Spinner size="sm" decorative />
                {LOADING_MAP_TEXT}
            </div>
            <aside className="cc-side" aria-hidden="true">
                <h2
                    style={{
                        color: '#ff3131',
                        fontSize: 38,
                        fontWeight: 600,
                        lineHeight: '44px',
                    }}
                >
                    CASE SPOTTING
                </h2>
                <div className="cc-sh">Sector</div>
                <div style={{ display: 'flex', gap: 8 }}>
                    {[56, 56, 56, 56, 150].map((w, i) => (
                        <Bone key={i} w={w} h={28} round />
                    ))}
                </div>
                <div className="cc-sh">Indicator</div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {[96, 80, 110, 90, 120, 100, 76].map((w, i) => (
                        <Bone key={i} w={w} h={28} round />
                    ))}
                </div>
                <LegendSkeleton />
                <Bone w="100%" h={60} style={{ borderRadius: 14 }} />
                <div className="cc-sh">
                    <Bone w={160} h={10} />
                </div>
                <TopRowsSkeleton />
            </aside>
        </>
    );
}

/** The colour scale with its two end values, while values load. */
export function LegendSkeleton() {
    return (
        <div className="cc-lgd" aria-hidden="true">
            <Bone w={40} h={10} />
            <Bone w="100%" h={10} round />
            <Bone w={40} h={10} />
        </div>
    );
}

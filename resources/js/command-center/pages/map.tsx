import { useMemo, useRef, useState } from 'react';
import { useCommand } from '../context';
import { fmt, missingText } from '../format';
import {
    area,
    centre,
    colour,
    geoPolygons,
    outlinePath,
    pathOf,
    project,
    voronoiCells,
    type Pt,
} from '../map-geometry';
import { useTip } from '../tooltip';
import type {
    Envelope,
    GeoJson,
    MapData,
    MapDistrict,
    MapIndicator,
    MapSector,
} from '../types';
import { Chip, LoadState } from '../ui';
import { useApi } from '../use-api';
import { usePhone } from '../use-phone';

const SECTORS: { key: MapSector; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'public', label: 'Public' },
    { key: 'private', label: 'Private' },
    { key: 'ppm', label: 'PPM' },
];

/** Which district field feeds each indicator. */
const FIELD: Record<string, keyof MapDistrict> = {
    suspects: 'suspects',
    positivity: 'positivity',
    median_tat: 'tat_days',
    lost_to_follow_up: 'ltfu_pct',
    treatment_gap: 'treatment_gap',
    testing_coverage: 'coverage_pct',
    ai_tests: 'ai_tests',
};

const valueOf = (d: MapDistrict, indicator: string): number | null => {
    const v = d[FIELD[indicator] ?? 'positivity'];

    return typeof v === 'number' ? v : null;
};

const show = (indicator: MapIndicator, v: number | null): string => {
    if (v === null) {
        return missingText();
    }

    const f = indicator.format;

    if (f.type === 'percent') {
        return `${v.toFixed(f.decimals ?? 1)}%`;
    }

    if (f.type === 'decimal') {
        return `${v.toFixed(f.decimals ?? 1)}${f.suffix ?? ''}`;
    }

    return fmt(v);
};

const num = (v: number | null, digits: number, suffix: string): string =>
    v === null ? 'n/a' : `${v.toFixed(digits)}${suffix}`;

/** Drawn shape for one district: its rings, label point and area. */
type Shape = {
    district: MapDistrict;
    rings: Pt[][];
    centre: Pt;
    area: number;
};

/** The map is at most this many stage pixels wide and tall when zoomed to a scope. */
const BOX = { x0: 420, y0: 112, x1: 930, y1: 640 };

const MIN_FOR_SCHEMATIC = 20;

export function MapPage() {
    const { mapIntent, setMapIntent } = useCommand();
    const phone = usePhone();
    const sector = mapIntent.sector;
    const state = useApi<Envelope<MapData>>(
        `/api/command/map?sector=${sector}`,
    );
    const last = useRef<Envelope<MapData> | null>(null);
    const [mode, setMode] = useState<'schematic' | 'boundaries'>('schematic');

    if (state.data) {
        last.current = state.data;
    }

    const body = (state.data ?? last.current)?.data ?? null;
    const districts = useMemo(() => body?.districts ?? [], [body]);
    const canSchematic =
        districts.length >= MIN_FOR_SCHEMATIC &&
        districts.every((d) => d.seat !== null);
    const useGeo = !canSchematic || mode === 'boundaries';
    const geo = useApi<GeoJson>(useGeo && body ? body.geometry.url : null);

    if (!body) {
        return (
            <LoadState
                loading={state.loading}
                error={state.error}
                onRetry={state.reload}
            />
        );
    }

    return (
        <MapView
            key={body.districts.length}
            body={body}
            sector={sector}
            phone={phone}
            canSchematic={canSchematic}
            useGeo={useGeo}
            geo={geo.data}
            geoError={geo.error}
            loading={state.loading}
            toggleMode={() =>
                setMode((m) => (m === 'schematic' ? 'boundaries' : 'schematic'))
            }
            intent={mapIntent}
            setIntent={setMapIntent}
        />
    );
}

type ViewProps = {
    body: MapData;
    sector: MapSector;
    phone: boolean;
    canSchematic: boolean;
    useGeo: boolean;
    geo: GeoJson | null;
    geoError: string | null;
    loading: boolean;
    toggleMode: () => void;
    intent: ReturnType<typeof useCommand>['mapIntent'];
    setIntent: ReturnType<typeof useCommand>['setMapIntent'];
};

function MapView({
    body,
    sector,
    phone,
    canSchematic,
    useGeo,
    geo,
    geoError,
    loading,
    toggleMode,
    intent,
    setIntent,
}: ViewProps) {
    const tip = useTip();
    const districts = body.districts;
    const indicator =
        body.indicators.find((i) => i.key === intent.indicator) ??
        body.indicators.find((i) => i.key === 'positivity') ??
        body.indicators[0];
    const heatOn = intent.heat;
    const selected =
        districts.find((d) => d.city_id === intent.selected)?.city_id ??
        (districts.length === 1 ? districts[0].city_id : null);

    const shapes = useMemo<Shape[]>(() => {
        if (!useGeo) {
            const cells = voronoiCells(
                body.schematic.outline as Pt[],
                districts.map((d) => d.seat as Pt),
            );

            return districts.map((district, i) => ({
                district,
                rings: [cells[i]],
                centre: centre(cells[i]),
                area: area(cells[i]),
            }));
        }

        if (!geo) {
            return [];
        }

        const rings = geoPolygons(geo, body.geometry.match_property);

        return districts.flatMap((district) => {
            const own = rings.get(district.match_key);

            if (!own || own.length === 0) {
                return [];
            }

            const main = own.reduce((a, b) => (area(b) > area(a) ? b : a));

            return [
                {
                    district,
                    rings: own,
                    centre: centre(main),
                    area: own.reduce((sum, r) => sum + area(r), 0),
                },
            ];
        });
    }, [useGeo, geo, body, districts]);

    const fit = useMemo(() => {
        if (districts.length >= MIN_FOR_SCHEMATIC || shapes.length === 0) {
            return { k: 1, x: 0, y: 0 };
        }

        const pts = shapes.flatMap((s) => s.rings.flat());
        const xs = pts.map((p) => p[0]);
        const ys = pts.map((p) => p[1]);
        const w = Math.max(...xs) - Math.min(...xs);
        const h = Math.max(...ys) - Math.min(...ys);
        const k = Math.min(
            (BOX.x1 - BOX.x0) / (w || 1),
            (BOX.y1 - BOX.y0) / (h || 1),
            5,
        );

        return {
            k,
            x: BOX.x0 + (BOX.x1 - BOX.x0 - w * k) / 2 - Math.min(...xs) * k,
            y: BOX.y0 + (BOX.y1 - BOX.y0 - h * k) / 2 - Math.min(...ys) * k,
        };
    }, [districts.length, shapes]);

    const values = districts
        .map((d) => valueOf(d, indicator.key))
        .filter((v): v is number => v !== null);
    const mn = values.length ? Math.min(...values) : 0;
    const mx = values.length ? Math.max(...values) : 0;
    const norm = (v: number) => (v - mn) / (mx - mn || 1);

    const top = useMemo(
        () =>
            districts
                .filter((d) => valueOf(d, indicator.key) !== null)
                .sort(
                    (a, b) =>
                        ((valueOf(b, indicator.key) as number) -
                            (valueOf(a, indicator.key) as number)) *
                        (indicator.lowest_is_worst ? -1 : 1),
                )
                .slice(0, 5),
        [districts, indicator],
    );

    const pulse = districts
        .filter((d) => body.pulse_top3.includes(d.city_id))
        .sort((a, b) => b.positive - a.positive);
    const chosen = districts.find((d) => d.city_id === selected) ?? null;
    const drawOrder = [...shapes].sort(
        (a, b) =>
            Number(a.district.city_id === selected) -
            Number(b.district.city_id === selected),
    );
    const sectorLabel = SECTORS.find((s) => s.key === sector)?.label ?? 'All';
    const karachi = project(66.55, 24.5);
    const seatXY = (d: MapDistrict): Pt => {
        const [x, y] = project(
            (d.seat as Pt | null)?.[0] ?? 0,
            (d.seat as Pt | null)?.[1] ?? 0,
        );

        return [x, y];
    };

    const select = (id: number) => setIntent({ selected: id });
    const clipId = 'cc-map-clip';
    const t = `translate(${fit.x} ${fit.y}) scale(${fit.k})`;
    const noBlur = phone;

    const sp = chosen?.trend12 ?? [];
    const sm = Math.max(...sp, 1);

    return (
        <>
            <svg
                className="cc-abs cc-map"
                style={{ left: 0, top: 0, pointerEvents: 'none' }}
                width={phone ? undefined : 1456}
                height={phone ? undefined : 819}
                viewBox={phone ? '390 96 560 560' : '0 0 1456 819'}
                role="group"
                aria-label={
                    useGeo
                        ? 'Map of Sindh districts, indicative boundaries'
                        : 'Schematic map of Sindh districts'
                }
                aria-busy={loading}
            >
                <defs>
                    <clipPath id={clipId}>
                        {useGeo ? (
                            shapes.flatMap((s) =>
                                s.rings.map((r, i) => (
                                    <path
                                        key={`${s.district.city_id}-${i}`}
                                        d={pathOf(r)}
                                    />
                                )),
                            )
                        ) : (
                            <path
                                d={outlinePath(body.schematic.outline as Pt[])}
                            />
                        )}
                    </clipPath>
                    <filter
                        id="cc-map-blur"
                        x="-30%"
                        y="-30%"
                        width="160%"
                        height="160%"
                    >
                        <feGaussianBlur stdDeviation="13" />
                    </filter>
                    <filter id="cc-map-glow">
                        <feGaussianBlur stdDeviation="7" />
                    </filter>
                </defs>

                {!useGeo ? (
                    <path
                        d={outlinePath(body.schematic.outline as Pt[])}
                        fill="none"
                        stroke="#7fe6fa"
                        strokeWidth="8"
                        opacity=".45"
                        filter="url(#cc-map-glow)"
                    />
                ) : null}

                <g transform={t}>
                    <g style={{ pointerEvents: 'auto' }}>
                        {drawOrder.map((s) => {
                            const d = s.district;
                            const v = valueOf(d, indicator.key);
                            const on = d.city_id === selected;
                            const text = (
                                <>
                                    <b>{d.name}</b>
                                    <br />
                                    {indicator.label}: {show(indicator, v)}
                                    <br />
                                    TB positive:{' '}
                                    {d.positive.toLocaleString('en-US')}
                                </>
                            );

                            return (
                                <path
                                    key={d.city_id}
                                    className="cc-cell"
                                    d={s.rings.map((r) => pathOf(r)).join('')}
                                    fill={
                                        v === null ? '#9aa5c8' : colour(norm(v))
                                    }
                                    stroke={
                                        on ? '#fff' : 'rgba(255,255,255,.5)'
                                    }
                                    strokeWidth={on ? 3.2 : 1}
                                    strokeLinejoin="round"
                                    vectorEffect="non-scaling-stroke"
                                    role="button"
                                    tabIndex={0}
                                    aria-pressed={on}
                                    aria-label={`${d.name}. ${indicator.label}: ${show(indicator, v)}. TB positive ${d.positive.toLocaleString('en-US')}`}
                                    onClick={() => select(d.city_id)}
                                    onKeyDown={(e) => {
                                        if (
                                            e.key === 'Enter' ||
                                            e.key === ' '
                                        ) {
                                            e.preventDefault();
                                            select(d.city_id);
                                        }
                                    }}
                                    {...tip(text, 'map')}
                                />
                            );
                        })}
                    </g>

                    {heatOn ? (
                        <g
                            clipPath={`url(#${clipId})`}
                            filter={noBlur ? undefined : 'url(#cc-map-blur)'}
                        >
                            {districts.map((d) => {
                                const [x, y] = seatXY(d);

                                return d.seat ? (
                                    <circle
                                        key={d.city_id}
                                        cx={x}
                                        cy={y}
                                        r={
                                            (14 + d.heat * 38) /
                                            (useGeo ? fit.k : 1)
                                        }
                                        fill="#ff1a1a"
                                        opacity={0.2 + 0.65 * d.heat}
                                    />
                                ) : null;
                            })}
                        </g>
                    ) : null}

                    {!useGeo ? (
                        <path
                            d={outlinePath(body.schematic.outline as Pt[])}
                            fill="none"
                            stroke="#fff"
                            strokeWidth="2"
                            strokeLinejoin="round"
                        />
                    ) : null}

                    <g>
                        {shapes.map((s) =>
                            s.area * fit.k * fit.k > 1500 ? (
                                <text
                                    key={s.district.city_id}
                                    className="cc-lb"
                                    x={s.centre[0].toFixed(0)}
                                    y={s.centre[1].toFixed(0)}
                                    textAnchor="middle"
                                    style={{
                                        fontSize: 9.5 / fit.k,
                                        strokeWidth: 2.5 / fit.k,
                                    }}
                                >
                                    {s.district.short_name}
                                </text>
                            ) : null,
                        )}
                        {fit.k === 1 ? (
                            <text
                                x={karachi[0]}
                                y={karachi[1]}
                                fill="#fff"
                                fontSize="13"
                                opacity=".85"
                            >
                                Karachi ▸
                            </text>
                        ) : null}
                    </g>

                    {heatOn ? (
                        <g>
                            {pulse.map((d) => {
                                const [x, y] = seatXY(d);

                                return d.seat ? (
                                    <g key={d.city_id}>
                                        <circle
                                            className="cc-pulse"
                                            cx={x}
                                            cy={y}
                                            r={4 / fit.k}
                                            fill="#ff3131"
                                            stroke="#fff"
                                        />
                                        <circle
                                            cx={x}
                                            cy={y}
                                            r={4 / fit.k}
                                            fill="#ff3131"
                                            stroke="#fff"
                                            strokeWidth={1 / fit.k}
                                        />
                                    </g>
                                ) : null;
                            })}
                        </g>
                    ) : null}
                </g>
            </svg>

            <p className="cc-map-note" style={{ left: 405, top: 656 }}>
                {useGeo
                    ? `${body.geometry.attribution}${geoError ? ' (boundaries could not load)' : ''}`
                    : 'Schematic district layout.'}{' '}
                {canSchematic ? (
                    <button
                        type="button"
                        className="cc-link"
                        aria-pressed={useGeo}
                        onClick={toggleMode}
                    >
                        {useGeo
                            ? 'Show schematic layout'
                            : 'Show indicative boundaries'}
                    </button>
                ) : null}
            </p>

            <aside className="cc-side" aria-label="Case spotting">
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
                <div className="cc-cr">
                    {SECTORS.map((s) => (
                        <Chip
                            key={s.key}
                            on={s.key === sector}
                            onClick={() => setIntent({ sector: s.key })}
                        >
                            {s.label}
                        </Chip>
                    ))}
                    <Chip
                        on={heatOn}
                        onClick={() => setIntent({ heat: !heatOn })}
                    >
                        ● TB positive (count)
                    </Chip>
                </div>
                <div className="cc-sh">Indicator</div>
                <div className="cc-cr">
                    {body.indicators.map((i) => (
                        <Chip
                            key={i.key}
                            on={i.key === indicator.key}
                            onClick={() => setIntent({ indicator: i.key })}
                        >
                            {i.label}
                        </Chip>
                    ))}
                </div>
                <div
                    className="cc-lgd"
                    role="img"
                    aria-label={`Scale from ${show(indicator, values.length ? mn : null)} to ${show(indicator, values.length ? mx : null)}`}
                >
                    <span>{show(indicator, values.length ? mn : null)}</span>
                    <i />
                    <span>{show(indicator, values.length ? mx : null)}</span>
                </div>

                {chosen ? (
                    <div className="cc-card" aria-live="polite">
                        <b style={{ fontSize: 17 }}>{chosen.name}</b>{' '}
                        <span style={{ opacity: 0.7 }}>· {sectorLabel}</span>
                        <div className="cc-gr">
                            {(
                                [
                                    ['Suspects', fmt(chosen.suspects)],
                                    ['Tested', fmt(chosen.tested)],
                                    ['TB positive', fmt(chosen.positive)],
                                    [
                                        'Positivity',
                                        num(chosen.positivity, 1, '%'),
                                    ],
                                    [
                                        'Started tx',
                                        num(chosen.started_pct, 0, '%'),
                                    ],
                                    [
                                        'Success',
                                        num(chosen.success_pct, 0, '%'),
                                    ],
                                    ['LTFU', num(chosen.ltfu_pct, 1, '%')],
                                    [
                                        'Median TAT',
                                        num(chosen.tat_days, 1, ' d'),
                                    ],
                                    ['MDR/RR-TB', String(chosen.mdr_rr)],
                                    [
                                        'AI tests',
                                        chosen.ai_tests === null
                                            ? null
                                            : fmt(chosen.ai_tests),
                                    ],
                                    ['Active BMUs', String(chosen.bmus)],
                                    [
                                        'Coverage',
                                        num(chosen.coverage_pct, 0, '%'),
                                    ],
                                ] as const
                            )
                                .filter(([, value]) => value !== null)
                                .map(([label, value]) => (
                                    <span key={label}>
                                        {label}
                                        <b>{value}</b>
                                    </span>
                                ))}
                        </div>
                        <Sparkline
                            values={sp}
                            max={sm}
                            months={body.trend_months}
                        />
                        <span style={{ opacity: 0.6, fontSize: 11 }}>
                            12-month TB positive trend
                        </span>
                    </div>
                ) : (
                    <div className="cc-card" style={{ opacity: 0.75 }}>
                        Click a district to open its detail card.
                    </div>
                )}

                <div className="cc-sh">
                    Top 5 · {indicator.label}
                    {indicator.lowest_is_worst ? ' (lowest)' : ''}
                </div>
                {top.map((d, k) => (
                    <button
                        type="button"
                        key={d.city_id}
                        className={`cc-tr${d.city_id === selected ? ' on' : ''}`}
                        aria-pressed={d.city_id === selected}
                        onClick={() => select(d.city_id)}
                    >
                        <span>
                            {k + 1}. {d.name}
                        </span>
                        <b>{show(indicator, valueOf(d, indicator.key))}</b>
                    </button>
                ))}
            </aside>
        </>
    );
}

function Sparkline({
    values,
    max,
    months,
}: {
    values: number[];
    max: number;
    months: string[];
}) {
    const points = values
        .map((y, k) => `${k * 37 + 4},${34 - (y / max) * 30}`)
        .join(' ');

    return (
        <svg
            className="cc-svg-inline"
            width="410"
            height="38"
            role="img"
            aria-label={`TB positive by month, ${months[0] ?? ''} to ${months[months.length - 1] ?? ''}: ${values.join(', ')}`}
        >
            <polyline
                fill="none"
                stroke="#7fe6fa"
                strokeWidth="2.5"
                points={points}
            />
        </svg>
    );
}

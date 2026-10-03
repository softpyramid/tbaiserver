import { useEffect, useMemo, useState } from 'react';

type Position = [number, number];

type Polygon = {
    type: 'Polygon';
    coordinates: Position[][];
};

type MultiPolygon = {
    type: 'MultiPolygon';
    coordinates: Position[][][];
};

type Feature = {
    type: 'Feature';
    properties: {
        name?: string;
        match?: string;
    };
    geometry: Polygon | MultiPolygon;
};

type Collection = {
    features: Feature[];
};

export type MapDistrict = {
    id: string;
    match: string;
    name: string;
    display: string;
    value: number | null;
};

type PathShape = {
    key: string;
    match: string;
    name: string;
    d: string;
};

const viewWidth = 1000;

function isPosition(value: unknown): value is Position {
    return Array.isArray(value) && typeof value[0] === 'number' && typeof value[1] === 'number';
}

function ringsOf(geometry: Polygon | MultiPolygon): Position[][] | null {
    switch (geometry.type) {
        case 'Polygon':
            return geometry.coordinates.every((ring) => ring.every(isPosition)) ? geometry.coordinates : null;
        case 'MultiPolygon':
            return geometry.coordinates.every((polygon) => polygon.every((ring) => ring.every(isPosition)))
                ? geometry.coordinates.flat()
                : null;
        default: {
            const unreachable: never = geometry;

            return unreachable;
        }
    }
}

function projectCollection(collection: Collection): { paths: PathShape[]; height: number } | null {
    const features = collection.features.filter(
        (feature) => feature.geometry?.type === 'Polygon' || feature.geometry?.type === 'MultiPolygon',
    );

    if (features.length === 0) {
        return null;
    }

    let minLon = Infinity;
    let maxLon = -Infinity;
    let minLat = Infinity;
    let maxLat = -Infinity;

    for (const feature of features) {
        const rings = ringsOf(feature.geometry);

        if (rings === null) {
            return null;
        }

        for (const ring of rings) {
            for (const [lon, lat] of ring) {
                minLon = Math.min(minLon, lon);
                maxLon = Math.max(maxLon, lon);
                minLat = Math.min(minLat, lat);
                maxLat = Math.max(maxLat, lat);
            }
        }
    }

    const lonSpan = maxLon - minLon;
    const latSpan = maxLat - minLat;

    if (!Number.isFinite(lonSpan) || !Number.isFinite(latSpan) || lonSpan <= 0 || latSpan <= 0) {
        return null;
    }

    const height = Math.max(1, Math.round((viewWidth * latSpan) / lonSpan));
    const pad = 12;
    const innerWidth = viewWidth - pad * 2;
    const innerHeight = height - pad * 2;

    const paths = features.map((feature, index) => {
        const rings = ringsOf(feature.geometry) ?? [];
        const d = rings
            .map((ring) =>
                ring
                    .map(([lon, lat], pointIndex) => {
                        const x = pad + ((lon - minLon) / lonSpan) * innerWidth;
                        const y = pad + ((maxLat - lat) / latSpan) * innerHeight;

                        return `${pointIndex === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
                    })
                    .join(' '),
            )
            .join(' ');

        return {
            key: feature.properties.match ?? String(index),
            match: feature.properties.match ?? '',
            name: feature.properties.name ?? '',
            d: `${d} Z`,
        };
    });

    return { paths, height };
}

export function DistrictHeatMap({
    geometryUrl,
    districts,
    selectedId,
    onSelect,
}: {
    geometryUrl: string;
    districts: MapDistrict[];
    selectedId: string;
    onSelect: (districtId: string, unmatchedName: string | null) => void;
}) {
    const [collection, setCollection] = useState<Collection | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;

        fetch(geometryUrl)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('map');
                }

                return response.json() as Promise<unknown>;
            })
            .then((payload) => {
                if (cancelled) {
                    return;
                }

                if (
                    typeof payload !== 'object' ||
                    payload === null ||
                    !Array.isArray((payload as { features?: unknown }).features)
                ) {
                    setFailed(true);

                    return;
                }

                setCollection(payload as Collection);
            })
            .catch(() => {
                if (!cancelled) {
                    setFailed(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [geometryUrl]);

    const drawn = useMemo(() => {
        if (collection === null) {
            return null;
        }

        try {
            return projectCollection(collection);
        } catch {
            return null;
        }
    }, [collection]);

    const mapReady = !failed && drawn !== null && drawn.paths.length > 0;
    const byMatch = new Map(districts.map((district) => [district.match, district]));
    const values = districts.map((district) => district.value).filter((value): value is number => value !== null);
    const min = values.length === 0 ? 0 : Math.min(...values);
    const max = values.length === 0 ? 0 : Math.max(...values);

    return (
        <div className="min-w-0">
            {mapReady && drawn ? (
                <div className="hidden min-w-0 md:block" data-test="district-map">
                    <svg
                        viewBox={`0 0 ${viewWidth} ${drawn.height}`}
                        className="block h-auto w-full max-w-full text-primary"
                        role="img"
                        aria-label="Sindh districts"
                    >
                        {drawn.paths.map((path) => {
                            const district = byMatch.get(path.match);
                            const selected = district !== undefined && district.id === selectedId;
                            const shade =
                                district?.value === null || district?.value === undefined
                                    ? null
                                    : max === min
                                      ? 0.55
                                      : 0.25 + ((district.value - min) / (max - min)) * 0.75;

                            return (
                                <path
                                    key={path.key}
                                    d={path.d}
                                    tabIndex={0}
                                    role="button"
                                    aria-label={path.name}
                                    fill="currentColor"
                                    fillOpacity={shade === null ? 0.08 : shade}
                                    stroke="currentColor"
                                    strokeWidth={selected ? 8 : 2}
                                    className="cursor-pointer outline-none"
                                    onClick={() => onSelect(district?.id ?? '', district ? null : path.name)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            onSelect(district?.id ?? '', district ? null : path.name);
                                        }
                                    }}
                                />
                            );
                        })}
                    </svg>
                </div>
            ) : null}

            <div className={mapReady ? 'min-w-0 md:hidden' : 'min-w-0'} data-test="map-fallback">
                <ul className="flex flex-col gap-2">
                    {districts.map((district) => (
                        <li key={district.id}>
                            <button
                                type="button"
                                className="flex w-full min-w-0 items-baseline justify-between gap-3 rounded-lg border px-3 py-2 text-left"
                                onClick={() => onSelect(district.id, null)}
                            >
                                <span className="min-w-0 font-medium break-words">{district.name}</span>
                                <span className="shrink-0 text-sm tabular-nums">{district.display}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

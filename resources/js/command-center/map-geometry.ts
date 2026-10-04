import type { GeoJson } from './types';

export type Pt = [number, number];

/** The prototype's projection of lon/lat onto the 1456 x 819 stage. */
const S = 128;
const X0 = 405;
const Y0 = 110;

export const project = (lon: number, lat: number): Pt => [
    X0 + (lon - 66.5) * 0.9 * S,
    Y0 + (28.8 - lat) * S,
];

const clip = (poly: Pt[], a: number, b: number, c: number): Pt[] => {
    const out: Pt[] = [];

    poly.forEach((p, k) => {
        const q = poly[(k + 1) % poly.length];
        const fp = a * p[0] + b * p[1] - c;
        const fq = a * q[0] + b * q[1] - c;

        if (fp <= 0) {
            out.push(p);
        }

        if (fp * fq < 0) {
            const t = fp / (fp - fq);

            out.push([p[0] + t * (q[0] - p[0]), p[1] + t * (q[1] - p[1])]);
        }
    });

    return out;
};

/** Voronoi cells of the district seats, clipped to the outline (the prototype's schematic boundaries). */
export function voronoiCells(outline: Pt[], seats: Pt[]): Pt[][] {
    const base = outline.map((p) => project(p[0], p[1]));
    const xy = seats.map((s) => project(s[0], s[1]));

    return xy.map((a, i) => {
        let poly = base;

        xy.forEach((b, j) => {
            if (j !== i) {
                poly = clip(
                    poly,
                    2 * (b[0] - a[0]),
                    2 * (b[1] - a[1]),
                    b[0] ** 2 + b[1] ** 2 - a[0] ** 2 - a[1] ** 2,
                );
            }
        });

        return poly;
    });
}

export const outlinePath = (outline: Pt[]): string =>
    pathOf(outline.map((p) => project(p[0], p[1])));

export const pathOf = (poly: Pt[]): string =>
    `M${poly.map((q) => q.map((v) => v.toFixed(1)).join(',')).join('L')}Z`;

export const area = (poly: Pt[]): number =>
    Math.abs(
        poly.reduce((sum, q, k) => {
            const r = poly[(k + 1) % poly.length];

            return sum + q[0] * r[1] - r[0] * q[1];
        }, 0),
    ) / 2;

/** Average of the vertices, as the prototype places its labels. */
export const centre = (poly: Pt[]): Pt => [
    poly.reduce((a, q) => a + q[0], 0) / poly.length,
    poly.reduce((a, q) => a + q[1], 0) / poly.length,
];

/** Real boundaries: every ring of every district, projected. Keyed by match key. */
export function geoPolygons(
    geo: GeoJson,
    property: string,
): Map<string, Pt[][]> {
    const out = new Map<string, Pt[][]>();

    geo.features.forEach((f) => {
        const key = f.properties[property];
        const polygons =
            f.geometry.type === 'Polygon'
                ? [f.geometry.coordinates]
                : f.geometry.coordinates;
        const rings = polygons.map((poly) =>
            poly[0].map((c) => project(c[0], c[1])),
        );

        out.set(key, rings);
    });

    return out;
}

const RAMP: [number, number, number][] = [
    [74, 63, 224],
    [127, 230, 250],
    [217, 255, 0],
];

/** Blue to cyan to lime scale, t from 0 to 1. */
export function colour(t: number): string {
    const c = Math.max(0, Math.min(1, t));
    const s = c * 2;
    const i = Math.min(1, Math.floor(s));
    const f = s - i;
    const a = RAMP[i];
    const b = RAMP[i + 1];

    return `rgb(${a.map((v, k) => Math.round(v + (b[k] - v) * f)).join(',')})`;
}

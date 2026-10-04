export type Envelope<T, M = Record<string, unknown>> = {
    meta: M & { scope_name?: string; has_returns?: boolean };
    data: T;
};

export type Screen = {
    number: number;
    key: string;
    label: string;
    label_lines: string[];
    page_url: string;
    api_url: string;
    background: 'lab' | 'ajrak';
    accent: 'default' | 'alt';
};

export type Frame = {
    title: string;
    scope: {
        label: string;
        level: string;
        province_id: number | null;
        district_id: number | null;
        locks: { province: boolean; district: boolean };
    };
    screens: Screen[];
    flags: {
        drivers: boolean;
        interventions: boolean;
        incidence: boolean;
        ai_tests: boolean;
    };
    months: { month: string; label: string }[];
    assets: {
        base: string;
        backgrounds: Record<string, string>;
        logos: { sindh_govt: string; tbai: string };
        webp: boolean;
    };
    geometry: { url: string; match_property: string; attribution: string };
};

export type MetricStatus =
    | 'not_collected'
    | 'not_enough_returns'
    | 'provisional'
    | 'ok';

export type CascadeItem = {
    key: string;
    label: string;
    value: number | null;
    status?: MetricStatus;
};

export type RingItem = {
    key: string;
    label: string;
    value: number | null;
    max: number;
    unit: string;
    status?: MetricStatus;
};

export type TileItem = {
    key: string;
    label: string;
    value: number | null;
    unit: string | null;
    status?: MetricStatus;
};

export type CumulativeMonth = {
    month: string;
    label: string;
    tested: number | null;
    positive: number | null;
    started_treatment: number | null;
};

export type TableItem = {
    key: string;
    icon: string;
    label: string;
    meaning: string;
    value: number | null;
    unit: string | null;
    status?: MetricStatus;
};

export type Cumulative = {
    cascade: CascadeItem[];
    performance: { rings: RingItem[]; tiles: TileItem[] };
    months: CumulativeMonth[];
    table: TableItem[];
};

export type ColumnFormat =
    | { type: 'text' }
    | { type: 'int' }
    | { type: 'percent'; decimals: number }
    | { type: 'decimal'; decimals: number };

export type SectorColumn = {
    key: string;
    label: string;
    align: 'left' | 'right';
    format: ColumnFormat;
};

export type SectorRow = {
    city_id: number | null;
    district: string;
    match_key: string | null;
    [key: string]: string | number | null;
};

export type SectorTable = {
    columns: SectorColumn[];
    rows: SectorRow[];
    total: SectorRow;
    row_action: 'bmu_drilldown' | 'map';
};

export type BmuMonth = {
    month: string;
    label: string;
    tested: number | null;
    positive: number | null;
};

export type Bmu = {
    facility_id: number;
    name: string;
    label: string;
    suspects: number;
    tested: number;
    positive: number;
    positivity: number | null;
    success_pct: number | null;
    ltfu_pct: number | null;
    tat_days: number | null;
    share_of_district_positive: number | null;
    months: BmuMonth[];
};

export type BmuDrilldown = {
    district: { city_id: number; name: string; match_key: string };
    summary: {
        active_bmus: number;
        suspects: number;
        tested: number;
        positive: number;
    };
    bmus: Bmu[];
    map: { sector: string; indicator: string; selected: number };
};

export type Sector = 'public' | 'private' | 'ppm';
export type MapSector = 'all' | Sector;

export type MapIndicator = {
    key: string;
    label: string;
    format: {
        type: 'int' | 'percent' | 'decimal';
        decimals?: number;
        suffix?: string;
    };
    lowest_is_worst: boolean;
};

export type MapDistrict = {
    city_id: number;
    match_key: string;
    name: string;
    short_name: string;
    seat: [number, number] | null;
    suspects: number;
    tested: number;
    positive: number;
    positivity: number | null;
    started_pct: number | null;
    success_pct: number | null;
    ltfu_pct: number | null;
    tat_days: number | null;
    mdr_rr: number;
    ai_tests: number | null;
    bmus: number;
    treatment_gap: number;
    coverage_pct: number | null;
    value: number | null;
    heat: number;
    trend12: number[];
};

export type MapData = {
    indicators: MapIndicator[];
    sectors: MapSector[];
    scale: { min: number; max: number } | null;
    districts: MapDistrict[];
    top5: { city_id: number; name: string; value: number | null }[];
    pulse_top3: number[];
    hidden: string[];
    selected: number | null;
    trend_months: string[];
    geometry: {
        url: string;
        match_property: string;
        indicative: boolean;
        attribution: string;
    };
    schematic: { outline: [number, number][]; note: string };
};

export type GeoFeature = {
    properties: Record<string, string>;
    geometry:
        | { type: 'Polygon'; coordinates: [number, number][][] }
        | { type: 'MultiPolygon'; coordinates: [number, number][][][] };
};

export type GeoJson = { attribution?: string; features: GeoFeature[] };

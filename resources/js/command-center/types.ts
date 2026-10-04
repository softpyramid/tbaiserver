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

export type AlertLine = {
    severity: 'critical' | 'attention' | 'emerging' | 'improvement';
    text: string;
    metric: string;
    values: Record<string, number | string>;
    subject: { type: string; id: number | null; name: string };
    map: { sector: string; indicator: string; selected: number | null };
};

export type TopEightItem = {
    city_id: number;
    name: string;
    short_name: string;
    value: number;
    decimals: number;
};

export type TopEight = {
    key: string;
    metric: string;
    title_subject: string;
    scope: string;
    indicator: string;
    axis_decimals: number;
    value_decimals: number;
    unit: string | null;
    items: TopEightItem[];
};

export type Intelligence = {
    alert_center: { lines: AlertLine[]; all: AlertLine[] };
    top8: TopEight[];
};

export type DriverVariable = { key: string; label: string; available: boolean };

export type DriverCategory = {
    letter: string;
    key: string;
    label: string;
    indicator: string;
    lowest_is_worst: boolean;
    signals: string[];
    responses: string[];
    priority: { city_id: number; name: string; value: number }[];
};

export type Drivers = {
    variables: DriverVariable[];
    categories: DriverCategory[];
    default_category: string;
};

export type Incidence = {
    monthly: { month: string; label: string; value: number | null }[];
    yearly: { year: number; value: number | null; kind: string }[];
    defaults: { month: string; year: number; monthly_chart: 'bar' | 'line' };
};

export type IncidenceMeta = {
    province: { id: number; name: string } | null;
    y_axis: { min: number; max: number };
};

export type AiPatient = {
    id: string;
    summary: string;
    location: string;
    symptoms: string;
    probability: number;
    laboratory: string;
    history: string;
    city_id: number | null;
    level: 'high' | 'moderate' | 'low';
};

export type AiTests = {
    patients: AiPatient[];
    default_patient: string | null;
};

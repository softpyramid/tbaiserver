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

import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
} from '@/components/command-center';
import { DistrictHeatMap } from '@/components/district-heat-map';

type TrendMonth = {
    key: string;
    label: string;
    suspects: number;
    tested: number;
    positive: number;
    started_treatment: number;
};

type FigureLine = {
    key: string;
    label: string;
    value: string;
};

type District = {
    id: string;
    match: string;
    name: string;
    display: string;
    value: number | null;
    lines: FigureLine[];
    trend: TrendMonth[];
};

type TopItem = {
    id: string;
    name: string;
    display: string;
    value: number;
};

type Props = {
    title: string;
    scope: string;
    screens: CommandScreenLink[];
    filters: CommandFilters;
    options: {
        provinces: CommandOption[];
        districts: CommandOption[];
        sectors: CommandOption[];
    };
    show: CommandShow;
    labels: CommandLabels;
    links: CommandLink[];
    tat: {
        note: string;
        definition: string;
    };
    boundaries: string;
    attribution: string;
    geometryUrl: string;
    indicator: string;
    indicatorField: string;
    indicators: CommandOption[];
    districts: District[];
    top: TopItem[];
    selectedId: string;
    trendHeading: string;
    topHeading: string;
    chooseDistrict: string;
    listHeading: string;
};

function pathOf(url: string): string {
    return url.split('?')[0] ?? url;
}

export default function CommandMap({
    title,
    scope,
    screens,
    filters,
    options,
    show,
    labels,
    links,
    tat,
    boundaries,
    attribution,
    geometryUrl,
    indicator,
    indicatorField,
    indicators,
    districts,
    top,
    selectedId,
    trendHeading,
    topHeading,
    chooseDistrict,
    listHeading,
}: Props) {
    const page = usePage();
    const [pickedId, setPickedId] = useState(selectedId);
    const [unmatchedName, setUnmatchedName] = useState<string | null>(null);
    const selected = districts.find((district) => district.id === pickedId) ?? null;

    function onIndicator(value: string) {
        const query: Record<string, string> = { indicator: value };

        for (const [key, item] of Object.entries(filters)) {
            if (item !== '') {
                query[key] = item;
            }
        }

        router.get(pathOf(page.url), query, { preserveState: true, preserveScroll: true });
    }

    function onSelect(districtId: string, unmatched: string | null) {
        setPickedId(districtId);
        setUnmatchedName(unmatched);
    }

    return (
        <CommandShell
            title={title}
            scope={scope}
            screens={screens}
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
            preserved={{ indicator }}
        >
            <p data-test="indicative-boundaries">{boundaries}</p>
            <p className="text-sm text-muted-foreground" data-test="map-attribution">
                {attribution}
            </p>

            <label className="flex min-w-0 max-w-sm flex-col gap-1 text-sm">
                <span>{indicatorField}</span>
                <select
                    key={indicator}
                    name="indicator"
                    defaultValue={indicator}
                    aria-label={indicatorField}
                    className="border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 text-base md:text-sm"
                    onChange={(event) => onIndicator(event.target.value)}
                >
                    {indicators.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>

            <section className="min-w-0" aria-label={listHeading}>
                <DistrictHeatMap
                    geometryUrl={geometryUrl}
                    districts={districts}
                    selectedId={pickedId}
                    onSelect={onSelect}
                />
            </section>

            {selected ? (
                <DistrictCard
                    district={selected}
                    top={top}
                    topHeading={topHeading}
                    trendHeading={trendHeading}
                    labels={labels}
                />
            ) : (
                <p className="text-sm text-muted-foreground">
                    {unmatchedName ? `${unmatchedName}. ${labels.empty}` : chooseDistrict}
                </p>
            )}

            <div className="text-sm text-muted-foreground" data-test="tat-note">
                <p>{tat.definition}</p>
                <p>{tat.note}</p>
            </div>
        </CommandShell>
    );
}

function DistrictCard({
    district,
    top,
    topHeading,
    trendHeading,
    labels,
}: {
    district: District;
    top: TopItem[];
    topHeading: string;
    trendHeading: string;
    labels: CommandLabels;
}) {
    return (
        <section className="flex min-w-0 flex-col gap-4 rounded-lg border p-4" data-test="district-card">
            <h2 className="text-lg font-semibold break-words">{district.name}</h2>
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {district.lines.map((line) => (
                    <div key={line.key} className="min-w-0" data-test={`figure-${line.key}`}>
                        <dt className="text-sm text-muted-foreground">{line.label}</dt>
                        <dd className="break-words">{line.value}</dd>
                    </div>
                ))}
            </dl>

            <div className="min-w-0">
                <h3 className="mb-2 text-base font-medium">{topHeading}</h3>
                {top.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{labels.empty}</p>
                ) : (
                    <ol className="flex flex-col gap-1 text-sm" data-test="map-top">
                        {top.map((item) => (
                            <li key={item.id} className="flex min-w-0 justify-between gap-3">
                                <span className="min-w-0 break-words">{item.name}</span>
                                <span className="shrink-0 tabular-nums">{item.display}</span>
                            </li>
                        ))}
                    </ol>
                )}
            </div>

            <div className="min-w-0">
                <h3 className="mb-2 text-base font-medium">{trendHeading}</h3>
                <ul className="flex flex-col gap-2">
                    {district.trend.map((month) => (
                        <li
                            key={month.key}
                            className="grid grid-cols-2 gap-x-3 text-sm"
                            data-test={`trend-${month.key}`}
                        >
                            <span className="font-medium">{month.label}</span>
                            <span className="text-right tabular-nums">
                                {month.suspects} / {month.tested} / {month.positive} / {month.started_treatment}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

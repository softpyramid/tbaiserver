import { useState } from 'react';
import {
    type CascadeBar,
    CommandCharts,
    type CommandCrumb,
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
    type MonthPoint,
    SegmentedTabs,
    useChartMode,
    ViewToggle,
} from '@/components/command-center';
import { useEntryI18n } from '@/lib/i18n';

type FacilityChart = {
    id: string;
    name: string;
    cascade: CascadeBar[];
    months: MonthPoint[];
};

type Props = {
    title: string;
    sectorTitle: string;
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
    backUrl: string;
    crumbs: CommandCrumb[];
    facilities: FacilityChart[];
    hasReturns: boolean;
    tat: {
        note: string;
        definition: string;
    };
};

export default function CommandDistrict({
    title,
    scope,
    filters,
    options,
    show,
    labels,
    links,
    crumbs,
    facilities,
    hasReturns,
}: Props) {
    const { t } = useEntryI18n();
    const [mode, setMode] = useChartMode();
    const [selectedId, setSelectedId] = useState(facilities[0]?.id ?? '');
    const selected = facilities.find((facility) => facility.id === selectedId) ?? facilities[0] ?? null;
    const showFacilityTabs = facilities.length > 1;

    return (
        <CommandShell
            title={title}
            scope={scope}
            crumbs={crumbs}
            tabs={
                showFacilityTabs ? (
                    <SegmentedTabs
                        label={t('nav_facilities')}
                        tabs={facilities.map((facility) => ({
                            key: facility.id,
                            label: facility.name,
                            current: facility.id === selected?.id,
                            onSelect: () => setSelectedId(facility.id),
                        }))}
                    />
                ) : null
            }
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            {hasReturns ? null : <p className="text-sm text-muted-foreground">{labels.empty}</p>}

            <ViewToggle mode={mode} labels={labels} onChange={setMode} />

            {selected ? (
                <section className="min-w-0 rounded-lg border p-3" data-test="facility-chart">
                    <h2 className="mb-3 font-medium">{selected.name}</h2>
                    <CommandCharts
                        mode={mode}
                        cascade={selected.cascade}
                        months={selected.months}
                        labels={labels}
                    />
                </section>
            ) : null}
        </CommandShell>
    );
}

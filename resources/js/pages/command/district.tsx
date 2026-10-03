import { Link } from '@inertiajs/react';
import {
    type CascadeBar,
    CommandCharts,
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
    type MonthPoint,
    useChartMode,
    ViewToggle,
} from '@/components/command-center';

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
    facilities: FacilityChart[];
    hasReturns: boolean;
    tat: {
        note: string;
        definition: string;
    };
};

export default function CommandDistrict({
    title,
    sectorTitle,
    scope,
    screens,
    filters,
    options,
    show,
    labels,
    links,
    backUrl,
    facilities,
    hasReturns,
}: Props) {
    const [mode, setMode] = useChartMode();

    return (
        <CommandShell
            title={title}
            eyebrow={sectorTitle}
            scope={scope}
            screens={screens}
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            <Link href={backUrl} className="text-sm underline-offset-2 hover:underline">
                {labels.back}
            </Link>

            {hasReturns ? null : <p className="text-sm text-muted-foreground">{labels.empty}</p>}

            <ViewToggle mode={mode} labels={labels} onChange={setMode} />

            <div className="flex min-w-0 flex-col gap-6">
                <h2 className="text-base font-medium">{labels.facilities}</h2>
                {facilities.map((facility) => (
                    <section key={facility.id} className="min-w-0 rounded-lg border p-3" data-test="facility-chart">
                        <h3 className="mb-3 font-medium">{facility.name}</h3>
                        <CommandCharts
                            mode={mode}
                            cascade={facility.cascade}
                            months={facility.months}
                            labels={labels}
                        />
                    </section>
                ))}
            </div>
        </CommandShell>
    );
}

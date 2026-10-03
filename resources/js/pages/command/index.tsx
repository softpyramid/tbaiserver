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

type Kpi = {
    key: string;
    label: string;
    value: string;
};

type UnavailableItem = {
    key: string;
    status: 'not_collected';
    label: string;
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
    kpis: Kpi[];
    cascade: CascadeBar[];
    months: MonthPoint[];
    hasReturns: boolean;
    notCollected: {
        heading: string;
        items: UnavailableItem[];
    };
};

export default function CommandIndex({
    title,
    scope,
    filters,
    options,
    show,
    labels,
    links,
    kpis,
    cascade,
    months,
    hasReturns,
    notCollected,
}: Props) {
    const [mode, setMode] = useChartMode();

    return (
        <CommandShell
            title={title}
            scope={scope}
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            <ul className="grid grid-cols-2 gap-3 lg:grid-cols-3" data-test="command-kpis">
                {kpis.map((kpi) => (
                    <li
                        key={kpi.key}
                        className="min-w-0 rounded-lg border p-3"
                        data-test={`kpi-${kpi.key}`}
                    >
                        <div className="text-sm text-muted-foreground">{kpi.label}</div>
                        <div className="mt-1 text-lg font-semibold break-words">{kpi.value}</div>
                    </li>
                ))}
            </ul>

            <section className="flex min-w-0 flex-col gap-3" data-test="not-collected">
                <h2 className="text-sm font-medium text-muted-foreground">{notCollected.heading}</h2>
                <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {notCollected.items.map((item) => (
                        <li
                            key={item.key}
                            className="min-w-0 rounded-lg border border-dashed p-3 text-muted-foreground"
                            data-test={`not-collected-${item.key}`}
                        >
                            <div className="text-sm">{item.label}</div>
                            <div className="mt-1 text-sm">{notCollected.heading}</div>
                        </li>
                    ))}
                </ul>
            </section>

            {hasReturns ? null : <p className="text-sm text-muted-foreground">{labels.empty}</p>}

            <ViewToggle mode={mode} labels={labels} onChange={setMode} />
            <CommandCharts mode={mode} cascade={cascade} months={months} labels={labels} />
        </CommandShell>
    );
}

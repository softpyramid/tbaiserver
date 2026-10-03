import {
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
    type DistrictColumn,
    DistrictTable,
    type DistrictRow,
} from '@/components/command-center';

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
    columns: DistrictColumn[];
    rows: DistrictRow[];
    sort: string;
    direction: 'asc' | 'desc';
    hasReturns: boolean;
    tat: {
        note: string;
        definition: string;
    };
};

export default function CommandSector({
    title,
    scope,
    screens,
    filters,
    options,
    show,
    labels,
    links,
    columns,
    rows,
    sort,
    direction,
    hasReturns,
    tat,
}: Props) {
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
        >
            {hasReturns ? null : <p className="text-sm text-muted-foreground">{labels.empty}</p>}

            <DistrictTable
                columns={columns}
                rows={rows}
                sort={sort}
                direction={direction}
                filters={filters}
                labels={labels}
            />

            <div className="text-sm text-muted-foreground" data-test="tat-note">
                <p>{tat.definition}</p>
                <p>{tat.note}</p>
            </div>
        </CommandShell>
    );
}

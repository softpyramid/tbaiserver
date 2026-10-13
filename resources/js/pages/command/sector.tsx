import { usePage } from '@inertiajs/react';
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
    type PageTab,
    SegmentedTabs,
} from '@/components/command-center';
import { useEntryI18n } from '@/lib/i18n';

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
    const page = usePage();
    const { t } = useEntryI18n();
    const registeredTabs = sectorTabs(screens, page.props.nav.groups);

    return (
        <CommandShell
            title={title}
            scope={scope}
            tabs={
                <SegmentedTabs
                    label={t('nav_registered')}
                    tabs={registeredTabs}
                />
            }
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            {hasReturns ? null : (
                <p className="text-sm text-muted-foreground">{labels.empty}</p>
            )}

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

type SectorKey = 'public' | 'private' | 'ppm';

function isSectorKey(key: string): key is SectorKey {
    switch (key) {
        case 'public':
        case 'private':
        case 'ppm':
            return true;
        default:
            return false;
    }
}

function sectorTabs(
    screens: CommandScreenLink[],
    groups: {
        items: { key: string; children: { key: string; label: string }[] }[];
    }[],
): PageTab[] {
    const registered = groups
        .flatMap((group) => group.items)
        .find((item) => item.key === 'registered');

    return screens.flatMap((screen) => {
        if (!isSectorKey(screen.key)) {
            return [];
        }

        const child = registered?.children.find(
            (item) => item.key === screen.key,
        );

        return [
            {
                key: screen.key,
                label: child?.label ?? screen.label,
                href: screen.href,
                current: screen.current,
            },
        ];
    });
}

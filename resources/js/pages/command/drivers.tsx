import { Link } from '@inertiajs/react';
import {
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
} from '@/components/command-center';
import { Badge } from '@/components/ui/badge';

type District = {
    id: string;
    name: string;
    value: string;
    href: string;
};

type Category = {
    key: string;
    label: string;
    status: 'available' | 'unavailable';
    formula: string;
    signalLabel: string;
    actions: string[];
    districts: District[];
};

type Pending = {
    key: string;
    label: string;
    status: string;
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
    badge: string;
    categories: Category[];
    pending: Pending[];
    driverLabels: {
        actions: string;
        priority: string;
        unavailable: string;
        pending: string;
    };
};

export default function CommandDrivers({
    title,
    scope,
    screens,
    filters,
    options,
    show,
    links,
    labels,
    tat,
    badge,
    categories,
    pending,
    driverLabels,
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
            <Badge data-test="thresholds-badge">{badge}</Badge>
            <p className="text-sm text-muted-foreground">{tat.note}</p>

            <div className="flex min-w-0 flex-col gap-4">
                {categories.map((category) => (
                    <CategoryCard key={category.key} category={category} labels={driverLabels} />
                ))}
            </div>

            <section className="flex min-w-0 flex-col gap-3" data-test="pending-drivers">
                <h2 className="text-base font-medium">{driverLabels.pending}</h2>
                <ul className="flex flex-col gap-2">
                    {pending.map((item) => (
                        <li
                            key={item.key}
                            className="min-w-0 rounded-lg border border-dashed p-3"
                            data-test={`pending-${item.key}`}
                        >
                            <div className="text-sm">{item.label}</div>
                            <div className="mt-1 text-sm text-muted-foreground">{item.status}</div>
                        </li>
                    ))}
                </ul>
            </section>
        </CommandShell>
    );
}

function CategoryCard({
    category,
    labels,
}: {
    category: Category;
    labels: Props['driverLabels'];
}) {
    return (
        <section className="min-w-0 rounded-lg border p-3" data-test={`driver-${category.key}`}>
            <h2 className="text-base font-medium">{category.label}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{category.formula}</p>

            <h3 className="mt-3 text-sm font-medium">{labels.actions}</h3>
            <ul className="mt-1 list-disc pl-5 text-sm">
                {category.actions.map((action) => (
                    <li key={action}>{action}</li>
                ))}
            </ul>

            <h3 className="mt-3 text-sm font-medium">{labels.priority}</h3>
            {category.status === 'available' ? (
                <ul className="mt-1 flex flex-col gap-2" data-test={`priority-${category.key}`}>
                    {category.districts.map((district) => (
                        <li key={district.id} className="min-w-0 text-sm">
                            <Link href={district.href} className="underline-offset-2 hover:underline">
                                {district.name}
                            </Link>
                            <span className="text-muted-foreground">
                                {' '}
                                {category.signalLabel}: {district.value}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-1 text-sm text-muted-foreground" data-test={`unavailable-${category.key}`}>
                    {labels.unavailable}
                </p>
            )}
        </section>
    );
}

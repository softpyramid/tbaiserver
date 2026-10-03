import { Head, Link } from '@inertiajs/react';

type Line = {
    label: string;
    value: string;
};

type Props = {
    title: string;
    dateLabel: string;
    name: string;
    lines: Line[];
    links: { href: string; label: string }[];
};

export default function FacilityHome({ title, dateLabel, name, lines, links }: Props) {
    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-md flex-col gap-6 px-4 py-6">
                <p className="text-sm text-muted-foreground">{dateLabel}</p>
                <h1 className="text-xl font-semibold">{name}</h1>
                <dl className="flex flex-col gap-3">
                    {lines.map((line) => (
                        <div key={line.label}>
                            <dt className="text-sm text-muted-foreground">{line.label}</dt>
                            <dd>{line.value}</dd>
                        </div>
                    ))}
                </dl>
                <div className="flex flex-col gap-3">
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="font-medium underline-offset-2 hover:underline"
                        >
                            {link.label}
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

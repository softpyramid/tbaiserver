import { Head, Link } from '@inertiajs/react';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
};

type Column = {
    key: 'name' | 'email' | 'role';
    label: string;
};

type Props = {
    title: string;
    name: string;
    empty: string;
    columns: Column[];
    members: Member[];
    manageUrl: string | null;
    manageLabel: string;
};

export default function FacilityMembers({
    title,
    name,
    empty,
    columns,
    members,
    manageUrl,
    manageLabel,
}: Props) {
    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-md flex-col gap-6 px-4 py-6">
                <h1 className="text-xl font-semibold">{title}</h1>
                <p className="text-sm text-muted-foreground">{name}</p>

                {members.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <ul className="space-y-3">
                        {members.map((member) => (
                            <li key={member.id} className="rounded-lg border p-3">
                                {columns.map((column) => (
                                    <div key={column.key}>
                                        <div className="text-sm text-muted-foreground">
                                            {column.label}
                                        </div>
                                        <div>{member[column.key]}</div>
                                    </div>
                                ))}
                            </li>
                        ))}
                    </ul>
                )}

                {manageUrl ? (
                    <Link href={manageUrl} className="font-medium underline-offset-2 hover:underline">
                        {manageLabel}
                    </Link>
                ) : null}
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';
import TeamAreaShell from '@/components/team-area-shell';
import type { TeamAreaHeader } from '@/types/team-area';

type Props = {
    area: TeamAreaHeader;
    intro: string;
    roles: { role: string; access: string }[];
};

export default function FacilityRoles({ area, intro, roles }: Props) {
    return (
        <>
            <Head title={area.title} />
            <TeamAreaShell area={area}>
                <p className="max-w-2xl text-sm text-muted-foreground">{intro}</p>
                <ul className="divide-y rounded-xl border bg-card">
                    {roles.map((role) => (
                        <li key={role.role} className="px-4 py-3">
                            <p className="font-medium text-foreground">{role.role}</p>
                            <p className="mt-1 text-sm text-muted-foreground">{role.access}</p>
                        </li>
                    ))}
                </ul>
            </TeamAreaShell>
        </>
    );
}

import { Head } from '@inertiajs/react';
import { useState } from 'react';
import InviteMemberModal from '@/components/invite-member-modal';
import RowActions from '@/components/row-actions';
import TeamAreaShell, { TeamPager } from '@/components/team-area-shell';
import { Button } from '@/components/ui/button';
import type { PageLinks, RowAction, TeamAreaHeader } from '@/types/team-area';

type Invitation = {
    code: string;
    email: string;
    roleLabel: string;
    sent: string;
    actions: RowAction[];
};

type Props = {
    area: TeamAreaHeader;
    invitations: Invitation[];
    pagination: PageLinks;
    labels: {
        invite: string;
        empty: string;
        email: string;
        role: string;
        sent: string;
        actions: string;
        previous: string;
        next: string;
        heading: string;
    };
};

export default function FacilityInvites({ area, invitations, pagination, labels }: Props) {
    const [inviteOpen, setInviteOpen] = useState(false);

    return (
        <>
            <Head title={labels.heading} />
            <TeamAreaShell area={area}>
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold">{labels.heading}</h2>
                    {area.canInvite ? (
                        <Button type="button" data-test="invite-member-button" onClick={() => setInviteOpen(true)}>
                            {labels.invite}
                        </Button>
                    ) : null}
                </div>

                {invitations.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{labels.empty}</p>
                ) : (
                    <>
                        <ul className="space-y-3 md:hidden">
                            {invitations.map((invitation) => (
                                <li key={invitation.code} data-test="invitation-row" className="rounded-xl border bg-card p-4">
                                    <p className="font-medium">{invitation.email}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{invitation.roleLabel}</p>
                                    <div className="mt-3">
                                        <RowActions actions={invitation.actions} label={labels.actions} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <div className="hidden overflow-x-auto rounded-xl border bg-card md:block">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="px-4 py-3 font-medium">{labels.email}</th>
                                        <th className="px-4 py-3 font-medium">{labels.role}</th>
                                        <th className="px-4 py-3 font-medium">{labels.sent}</th>
                                        <th className="px-4 py-3">
                                            <span className="sr-only">{labels.actions}</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invitations.map((invitation) => (
                                        <tr key={invitation.code} data-test="invitation-row" className="border-b last:border-0">
                                            <td className="px-4 py-3">{invitation.email}</td>
                                            <td className="px-4 py-3">{invitation.roleLabel}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{invitation.sent}</td>
                                            <td className="px-4 py-3 text-right">
                                                <RowActions actions={invitation.actions} label={labels.actions} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                <TeamPager pagination={pagination} previousLabel={labels.previous} nextLabel={labels.next} />
            </TeamAreaShell>

            {area.canInvite ? (
                <InviteMemberModal
                    team={{ id: 0, name: area.teamName, slug: area.teamSlug, isPersonal: false }}
                    availableRoles={area.inviteRoles}
                    open={inviteOpen}
                    onOpenChange={setInviteOpen}
                />
            ) : null}
        </>
    );
}

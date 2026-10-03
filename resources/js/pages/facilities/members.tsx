import { Head, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import InviteMemberModal from '@/components/invite-member-modal';
import RowActions from '@/components/row-actions';
import TeamAreaShell, { TeamPager } from '@/components/team-area-shell';
import TeamSummaryCards from '@/components/team-summary-cards';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { PageLinks, RowAction, SummaryCard, TeamAreaHeader } from '@/types/team-area';

type Option = {
    value: string;
    label: string;
};

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    joined: string;
    lastActive: string;
    activeNow: boolean;
    returnsThisMonth: number;
    inactive: boolean;
    actions: RowAction[];
};

type Props = {
    area: TeamAreaHeader;
    cards: SummaryCard[];
    filters: { q: string; role: string; status: string };
    roleOptions: Option[];
    statusOptions: Option[];
    members: Member[];
    pagination: PageLinks;
    labels: {
        invite: string;
        search: string;
        role: string;
        status: string;
        apply: string;
        clear: string;
        actions: string;
        member: string;
        returns: string;
        joined: string;
        lastActive: string;
        empty: string;
        current: string;
        previous: string;
        next: string;
        inactive: string;
    };
};

function RoleBadge({ label }: { label: string }) {
    return (
        <span className="inline-flex rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-medium text-brand">
            {label}
        </span>
    );
}

function LastActive({ member }: { member: Member }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 text-sm',
                member.activeNow ? 'font-medium text-success' : 'text-muted-foreground',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'size-2 rounded-full',
                    member.activeNow ? 'bg-success' : 'bg-muted-foreground',
                )}
            />
            {member.lastActive}
        </span>
    );
}

function MemberIdentity({ member }: { member: Member }) {
    const initials = useInitials();

    return (
        <div className="flex min-w-0 items-center gap-3">
            <Avatar className="size-10">
                <AvatarFallback className="bg-muted text-sm font-medium text-foreground">
                    {initials(member.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="truncate font-medium text-foreground">{member.name}</p>
                <p className="truncate text-sm text-muted-foreground">{member.email}</p>
            </div>
        </div>
    );
}

export default function FacilityMembers({
    area,
    cards,
    filters,
    roleOptions,
    statusOptions,
    members,
    pagination,
    labels,
}: Props) {
    const [inviteOpen, setInviteOpen] = useState(false);

    function onFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const query: Record<string, string> = {};

        data.forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        });

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={area.title} />
            <TeamAreaShell area={area}>
                <div className="flex flex-col gap-3">
                    <div className="flex justify-end">
                        {area.canInvite ? (
                            <Button
                                type="button"
                                data-test="invite-member-button"
                                onClick={() => setInviteOpen(true)}
                            >
                                <UserPlus />
                                {labels.invite}
                            </Button>
                        ) : null}
                    </div>
                    <TeamSummaryCards cards={cards} />
                </div>

                <section className="flex flex-col gap-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <h2 className="text-lg font-semibold text-foreground">{labels.current}</h2>
                        <form
                            className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center"
                            onSubmit={onFilter}
                        >
                            <Input
                                name="q"
                                defaultValue={filters.q}
                                placeholder={labels.search}
                                aria-label={labels.search}
                                className="sm:col-span-2 lg:w-56"
                            />
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="sr-only">{labels.role}</span>
                                <select
                                    name="role"
                                    defaultValue={filters.role}
                                    aria-label={labels.role}
                                    className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-base md:text-sm lg:w-44"
                                >
                                    {roleOptions.map((option) => (
                                        <option key={`role-${option.value}`} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="sr-only">{labels.status}</span>
                                <select
                                    name="status"
                                    defaultValue={filters.status}
                                    aria-label={labels.status}
                                    className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-base md:text-sm lg:w-36"
                                >
                                    {statusOptions.map((option) => (
                                        <option key={`status-${option.value}`} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div className="flex gap-2 sm:col-span-2 lg:col-auto">
                                <Button type="submit">{labels.apply}</Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.get(window.location.pathname, {}, { preserveScroll: true })
                                    }
                                >
                                    {labels.clear}
                                </Button>
                            </div>
                        </form>
                    </div>

                    {members.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{labels.empty}</p>
                    ) : (
                        <>
                            <ul className="space-y-3 md:hidden">
                                {members.map((member) => (
                                    <li
                                        key={member.id}
                                        data-test="member-row"
                                        className="rounded-xl border bg-card p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <MemberIdentity member={member} />
                                            <RowActions actions={member.actions} label={labels.actions} />
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                            <RoleBadge label={member.roleLabel} />
                                            {member.inactive ? (
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    {labels.inactive}
                                                </span>
                                            ) : null}
                                        </div>
                                        <p className="mt-2">
                                            <LastActive member={member} />
                                        </p>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden overflow-x-auto rounded-xl border bg-card md:block">
                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="px-4 py-3 font-medium">{labels.member}</th>
                                            <th className="px-4 py-3 font-medium">{labels.returns}</th>
                                            <th className="px-4 py-3 font-medium">{labels.role}</th>
                                            <th className="px-4 py-3 font-medium">{labels.joined}</th>
                                            <th className="px-4 py-3 font-medium">{labels.lastActive}</th>
                                            <th className="px-4 py-3">
                                                <span className="sr-only">{labels.actions}</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {members.map((member) => (
                                            <tr
                                                key={member.id}
                                                data-test="member-row"
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <MemberIdentity member={member} />
                                                </td>
                                                <td className="px-4 py-3">{member.returnsThisMonth}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col items-start gap-1">
                                                        <RoleBadge label={member.roleLabel} />
                                                        {member.inactive ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                {labels.inactive}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {member.joined}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <LastActive member={member} />
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <RowActions
                                                        actions={member.actions}
                                                        label={labels.actions}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    <TeamPager
                        pagination={pagination}
                        previousLabel={labels.previous}
                        nextLabel={labels.next}
                    />
                </section>
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

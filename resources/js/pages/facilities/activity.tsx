import TeamAreaShell, { TeamPager } from '@/components/team-area-shell';
import type { PageLinks, TeamAreaHeader } from '@/types/team-area';

type EventRow = {
    id: number;
    when: string;
    who: string;
    action: string;
};

type Props = {
    area: TeamAreaHeader;
    events: EventRow[];
    pagination: PageLinks;
    labels: {
        empty: string;
        when: string;
        who: string;
        action: string;
        previous: string;
        next: string;
        heading: string;
    };
};

export default function FacilityActivity({
    area,
    events,
    pagination,
    labels,
}: Props) {
    return (
        <>
            <TeamAreaShell area={area}>
                {events.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                        {labels.empty}
                    </p>
                ) : (
                    <>
                        <ul className="space-y-3 md:hidden">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="rounded-xl border bg-card p-4"
                                >
                                    <p className="font-medium">
                                        {event.action}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {event.who}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {event.when}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        <div className="hidden overflow-x-auto rounded-xl border bg-card md:block">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="px-4 py-3 font-medium">
                                            {labels.when}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {labels.who}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {labels.action}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {event.when}
                                            </td>
                                            <td className="px-4 py-3">
                                                {event.who}
                                            </td>
                                            <td className="px-4 py-3">
                                                {event.action}
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
            </TeamAreaShell>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import EntryScreen from '@/components/entry-screen';
import {
    SuspectDraftNotice,
    SuspectReferFlag,
    SuspectUnavailable,
} from '@/components/suspect-notices';
import { Button } from '@/components/ui/button';
import { useEntryI18n } from '@/lib/i18n';

type Row = {
    id: string;
    date: string;
    patientName: string | null;
    registerNumber: string | null;
    ageBand: string;
    sex: string;
    completed: boolean;
    score: number | null;
    priority: string | null;
    priorityLabel: string | null;
    refer: boolean;
    href: string;
};

type Props = {
    facilityName: string;
    dateLabel: string;
    teamSlug: string;
    unavailable: string | null;
    draftNotice: string | null;
    canScreen: boolean;
    newUrl: string;
    rows: Row[];
};

export default function SuspectIndex({
    facilityName,
    dateLabel,
    unavailable,
    draftNotice,
    canScreen,
    newUrl,
    rows,
}: Props) {
    const { t } = useEntryI18n();

    return (
        <>
            <Head title={t('suspect_title')} />
            <EntryScreen
                title={t('suspect_title')}
                facilityName={facilityName}
                dateLabel={dateLabel}
            >
                {unavailable ? (
                    <SuspectUnavailable text={unavailable} />
                ) : (
                    <>
                        <SuspectDraftNotice text={draftNotice} />
                        {canScreen ? (
                            <Button
                                asChild
                                className="h-12 w-full text-base"
                                data-test="suspect-new"
                            >
                                <Link href={newUrl}>
                                    <Plus aria-hidden="true" />
                                    {t('suspect_new')}
                                </Link>
                            </Button>
                        ) : null}
                    </>
                )}

                {rows.length === 0 ? (
                    <p className="rounded-xl border bg-card p-4 text-base text-muted-foreground">
                        {t('suspect_list_empty')}
                    </p>
                ) : (
                    <ul
                        className="flex flex-col gap-2"
                        data-test="suspect-list"
                    >
                        {rows.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={row.href}
                                    className="flex flex-col gap-2 rounded-xl border bg-card p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none"
                                >
                                    <span className="text-sm text-muted-foreground">
                                        {row.date}
                                    </span>
                                    {row.patientName || row.registerNumber ? (
                                        <span className="text-base font-semibold text-foreground">
                                            {row.patientName}
                                            {row.registerNumber ? (
                                                <span className="font-normal text-muted-foreground">
                                                    {' · '}
                                                    {t(
                                                        'suspect_register_number',
                                                    )}
                                                    : {row.registerNumber}
                                                </span>
                                            ) : null}
                                        </span>
                                    ) : null}
                                    <span className="text-base text-foreground">
                                        {t('suspect_age_band')}: {row.ageBand}
                                        {' · '}
                                        {t('suspect_sex')}: {row.sex}
                                    </span>
                                    {row.completed ? (
                                        <span className="flex flex-wrap items-center gap-2 text-base">
                                            <span className="font-semibold">
                                                {t('column_score')}: {row.score}
                                            </span>
                                            {row.priorityLabel ? (
                                                <span>
                                                    {t('suspect_priority')}:{' '}
                                                    {row.priorityLabel}
                                                </span>
                                            ) : null}
                                            <SuspectReferFlag
                                                refer={row.refer}
                                            />
                                        </span>
                                    ) : (
                                        <span className="text-base text-muted-foreground">
                                            {t('suspect_in_progress')}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </EntryScreen>
        </>
    );
}

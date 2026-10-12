import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import EntryScreen from '@/components/entry-screen';
import { Button } from '@/components/ui/button';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Row = {
    id: string;
    read: boolean;
    date: string;
    text: string;
    patient: string | null;
    facility: string | null;
    href: string | null;
    readUrl: string;
};

type Props = {
    unread: number;
    readAllUrl: string;
    rows: Row[];
};

export default function NotificationsIndex({
    unread,
    readAllUrl,
    rows,
}: Props) {
    const { t } = useEntryI18n();

    return (
        <>
            <Head title={t('notifications_title')} />
            <EntryScreen
                title={t('notifications_title')}
                facilityName={t('notifications_title')}
                dateLabel=""
                headerless
            >
                <h1 className="flex items-center gap-2 text-lg font-semibold">
                    <Bell aria-hidden="true" className="size-5" />
                    {t('notifications_title')}
                </h1>

                {unread > 0 ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 w-full text-base"
                        data-test="notifications-read-all"
                        onClick={() =>
                            router.post(
                                readAllUrl,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CheckCheck aria-hidden="true" />
                        {t('notifications_mark_all_read')}
                    </Button>
                ) : null}

                {rows.length === 0 ? (
                    <p className="rounded-xl border bg-card p-4 text-base text-muted-foreground">
                        {t('notifications_empty')}
                    </p>
                ) : (
                    <ul
                        className="flex flex-col gap-2"
                        data-test="notifications-list"
                    >
                        {rows.map((row) => (
                            <li
                                key={row.id}
                                className={cn(
                                    'flex flex-col gap-2 rounded-xl border bg-card p-4',
                                    !row.read && 'border-l-4 border-l-red-700',
                                )}
                            >
                                <span className="text-sm text-muted-foreground">
                                    {row.date}
                                    {row.read ? null : (
                                        <span className="ml-2 font-semibold text-foreground">
                                            {t('notifications_unread')}
                                        </span>
                                    )}
                                </span>
                                <span className="text-base font-semibold">
                                    {row.text}
                                </span>
                                <span className="text-base">
                                    {row.patient ??
                                        t('notifications_patient_unavailable')}
                                    {row.facility ? (
                                        <span className="text-muted-foreground">
                                            {' · '}
                                            {row.facility}
                                        </span>
                                    ) : null}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {row.href ? (
                                        <Button
                                            asChild
                                            className="h-11 text-base"
                                        >
                                            <Link href={row.href}>
                                                {t(
                                                    'notifications_open_patient',
                                                )}
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {row.read ? null : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-11 text-base"
                                            onClick={() =>
                                                router.post(
                                                    row.readUrl,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('notifications_mark_read')}
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </EntryScreen>
        </>
    );
}

import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import EntryScreen from '@/components/entry-screen';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEntryI18n } from '@/lib/i18n';

type Row = {
    id: string;
    name: string;
    registerNumber: string;
    age: number;
    genderLabel: string;
    status: string;
    missed: number;
    href: string;
};

type Props = {
    facilityName: string;
    dateLabel: string;
    search: string;
    searchUrl: string;
    newUrl: string;
    previousUrl: string | null;
    nextUrl: string | null;
    rows: Row[];
};

export default function PatientsIndex({
    facilityName,
    dateLabel,
    search,
    searchUrl,
    newUrl,
    previousUrl,
    nextUrl,
    rows,
}: Props) {
    const { t } = useEntryI18n();
    const [query, setQuery] = useState(search);

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(searchUrl, query.trim() === '' ? {} : { q: query.trim() });
    }

    return (
        <>
            <Head title={t('patients_title')} />
            <EntryScreen
                title={t('patients_title')}
                facilityName={facilityName}
                dateLabel={dateLabel}
            >
                <Button
                    asChild
                    className="h-12 w-full text-base"
                    data-test="patients-new"
                >
                    <Link href={newUrl}>
                        <Plus aria-hidden="true" />
                        {t('patients_new')}
                    </Link>
                </Button>

                <form
                    role="search"
                    onSubmit={submit}
                    className="flex flex-col gap-2"
                    data-test="patients-search"
                >
                    <label htmlFor="q" className="text-base font-medium">
                        {t('patients_search')}
                    </label>
                    <div className="flex gap-2">
                        <Input
                            id="q"
                            name="q"
                            value={query}
                            autoComplete="off"
                            aria-describedby="q-hint"
                            className="h-12 text-base"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                        <Button type="submit" className="h-12 text-base">
                            <Search aria-hidden="true" />
                            {t('patients_search_button')}
                        </Button>
                    </div>
                    <p id="q-hint" className="text-sm text-muted-foreground">
                        {t('patients_search_hint')}
                    </p>
                    {search !== '' ? (
                        <Link
                            href={searchUrl}
                            className="w-fit text-base underline underline-offset-4"
                        >
                            {t('patients_search_clear')}
                        </Link>
                    ) : null}
                </form>

                {rows.length === 0 ? (
                    <p className="rounded-xl border bg-card p-4 text-base text-muted-foreground">
                        {search !== ''
                            ? t('patients_search_empty')
                            : t('patients_list_empty')}
                    </p>
                ) : (
                    <ul
                        className="flex flex-col gap-2"
                        data-test="patients-list"
                    >
                        {rows.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={row.href}
                                    className="flex flex-col gap-1 rounded-xl border bg-card p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none"
                                >
                                    <span className="text-base font-semibold text-foreground">
                                        {row.name}
                                    </span>
                                    <span className="text-base text-muted-foreground">
                                        {t('patients_register_number')}:{' '}
                                        {row.registerNumber}
                                    </span>
                                    <span className="text-base">
                                        {row.genderLabel} · {row.age}
                                    </span>
                                    <span className="flex flex-wrap items-center gap-2 text-base">
                                        {row.status}
                                        {row.missed > 0 ? (
                                            <span className="inline-flex items-center gap-1 rounded-md bg-red-700 px-2 py-0.5 text-xs font-semibold text-white">
                                                <TriangleAlert
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                                {t('patients_missed_badge', {
                                                    count: row.missed,
                                                })}
                                            </span>
                                        ) : null}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {previousUrl || nextUrl ? (
                    <nav className="flex justify-between gap-2">
                        {previousUrl ? (
                            <Button asChild variant="outline">
                                <Link href={previousUrl}>
                                    {t('previous_page')}
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {nextUrl ? (
                            <Button asChild variant="outline">
                                <Link href={nextUrl}>{t('next_page')}</Link>
                            </Button>
                        ) : null}
                    </nav>
                ) : null}
            </EntryScreen>
        </>
    );
}

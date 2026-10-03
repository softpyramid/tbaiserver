import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { today, update } from '@/routes/returns';

type Counts = {
    suspects: number;
    tested: number;
    positive: number;
    negative: number;
    started_treatment: number;
    follow_up: number;
    median_tat_days: string | number | null;
};

type HistoryChange = {
    field: string;
    from: number | null;
    to: number | null;
};

type HistoryEntry = {
    changedAt: string;
    changedBy: string;
    changes: HistoryChange[];
};

type Props = {
    facility: {
        name: string;
        sector: string;
        city: string;
    };
    reportedOn: string;
    isToday: boolean;
    earliestDate: string | null;
    latestDate: string;
    canSave: boolean;
    screeningSuspects: number;
    return: Counts;
    provisionalTat: {
        label: string;
        value: number | null;
        year: number;
    };
    history: HistoryEntry[] | null;
};

const fieldLabels: Record<string, string> = {
    suspects: 'Suspects',
    tested: 'Tested',
    positive: 'TB positive',
    negative: 'TB negative',
    started_treatment: 'Started on treatment',
    follow_up: 'Follow-up visits',
    median_tat_days: 'Median turnaround time',
};

const countFields: Array<{ name: keyof Counts; label: string; step?: string }> = [
    { name: 'suspects', label: 'Suspects identified' },
    { name: 'tested', label: 'Tested' },
    { name: 'positive', label: 'TB positive' },
    { name: 'negative', label: 'TB negative' },
    { name: 'started_treatment', label: 'Started on treatment' },
    { name: 'follow_up', label: 'Follow-up visits' },
    { name: 'median_tat_days', label: 'Median turnaround time, days', step: '0.01' },
];

export default function TodayRegister(props: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return <RegisterForm key={props.reportedOn} teamSlug={teamSlug} {...props} />;
}

function RegisterForm({
    facility,
    reportedOn,
    isToday,
    earliestDate,
    latestDate,
    canSave,
    screeningSuspects,
    return: dailyReturn,
    provisionalTat,
    history,
    teamSlug,
}: Props & { teamSlug: string }) {
    const [counts, setCounts] = useState({
        suspects: String(dailyReturn.suspects),
        tested: String(dailyReturn.tested),
        positive: String(dailyReturn.positive),
        negative: String(dailyReturn.negative),
        started_treatment: String(dailyReturn.started_treatment),
        follow_up: String(dailyReturn.follow_up),
        median_tat_days:
            dailyReturn.median_tat_days === null
                ? ''
                : String(dailyReturn.median_tat_days),
    });

    const tested = Number(counts.tested);
    const positive = Number(counts.positive);
    const positivity =
        Number.isFinite(tested) && tested > 0 && Number.isFinite(positive)
            ? positive / tested
            : null;

    const changeDate = (value: string) => {
        router.get(
            today.url(teamSlug, { query: { reported_on: value } }),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Today's register" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-8 px-4 py-6">
                <Heading
                    title="Today's register"
                    description={`${facility.name}. ${facility.sector}. ${facility.city}.`}
                />

                <Form
                    {...update.form(teamSlug)}
                    className="space-y-6"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="reported_on">Date</Label>
                                <Input
                                    id="reported_on"
                                    name="reported_on"
                                    type="date"
                                    value={reportedOn}
                                    min={earliestDate ?? undefined}
                                    max={latestDate}
                                    onChange={(event) =>
                                        changeDate(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.reported_on} />
                            </div>

                            {countFields.map((field) => (
                                <div key={field.name} className="grid gap-2">
                                    <Label htmlFor={field.name}>
                                        {field.label}
                                    </Label>
                                    <Input
                                        id={field.name}
                                        name={field.name}
                                        type="number"
                                        min="0"
                                        step={field.step ?? '1'}
                                        inputMode={
                                            field.step ? 'decimal' : 'numeric'
                                        }
                                        value={counts[field.name]}
                                        disabled={!canSave}
                                        onChange={(event) =>
                                            setCounts((current) => ({
                                                ...current,
                                                [field.name]: event.target.value,
                                            }))
                                        }
                                        required={field.name !== 'median_tat_days'}
                                    />
                                    {field.name === 'suspects' ? (
                                        <p className="text-sm text-muted-foreground">
                                            {isToday
                                                ? `Screenings marked suspect today: ${screeningSuspects}.`
                                                : `Screenings marked suspect on this date: ${screeningSuspects}.`}
                                        </p>
                                    ) : null}
                                    <InputError message={errors[field.name]} />
                                </div>
                            ))}

                            <p className="text-sm">
                                Positivity:{' '}
                                {positivity === null
                                    ? 'not available until tested is above zero'
                                    : `${(positivity * 100).toFixed(1)}%`}
                            </p>

                            {canSave ? (
                                <Button type="submit" disabled={processing}>
                                    Save return
                                </Button>
                            ) : null}
                        </>
                    )}
                </Form>

                <section className="space-y-2 text-sm">
                    <h2 className="font-medium">
                        Provisional. {provisionalTat.year}
                    </h2>
                    <p>
                        {provisionalTat.label}:{' '}
                        {provisionalTat.value === null
                            ? 'not enough returns'
                            : provisionalTat.value.toFixed(2)}
                    </p>
                    <p className="text-muted-foreground">
                        This figure is provisional until the official TAT
                        formula is confirmed.
                    </p>
                </section>

                {history !== null ? (
                    <section className="space-y-3" data-test="return-history">
                        <h2 className="font-medium">History</h2>
                        {history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No updates yet.
                            </p>
                        ) : (
                            <ol className="space-y-3 text-sm">
                                {history.map((entry) => (
                                    <li
                                        key={`${entry.changedAt}-${entry.changedBy}`}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="font-medium">
                                            {entry.changedBy}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {entry.changedAt}
                                        </p>
                                        <ul className="mt-2 space-y-1">
                                            {entry.changes.map((change) => (
                                                <li key={change.field}>
                                                    {fieldLabels[change.field] ??
                                                        change.field}
                                                    : {formatValue(change.from)}{' '}
                                                    to {formatValue(change.to)}
                                                </li>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                ) : null}
            </div>
        </>
    );
}

function formatValue(value: number | null): string {
    return value === null ? 'empty' : String(value);
}

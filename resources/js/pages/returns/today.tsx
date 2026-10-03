import { Form, Head, router } from '@inertiajs/react';
import { CloudOff } from 'lucide-react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import CountStepper from '@/components/count-stepper';
import EntryScreen from '@/components/entry-screen';
import FacilitySummaryCard from '@/components/facility-summary-card';
import FieldError from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { scrollToField } from '@/lib/entry-fields';
import { useEntryI18n } from '@/lib/i18n';
import {
    clearReturnDraft,
    readReturnDraft,
    writeReturnDraft,
    type CountState,
} from '@/lib/return-draft';
import {
    queueReturn,
    queuedReturn,
    removeQueuedReturn,
    subscribeReturnQueue,
    type QueuedReturn,
} from '@/lib/return-queue';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { cn } from '@/lib/utils';
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

type LastUpdated = {
    name: string;
    at: string;
    line: string;
};

type Props = {
    teamSlug: string;
    facility: {
        name: string;
        sector: string;
        city: string;
    };
    reportedOn: string;
    reportedOnLabel: string;
    isToday: boolean;
    todayDate: string;
    yesterdayDate: string;
    canChooseDate: boolean;
    earliestDate: string | null;
    latestDate: string;
    canSave: boolean;
    returnUpdatedAt: string | null;
    lastUpdated: LastUpdated | null;
    screeningSuspects: number;
    return: Counts;
    provisionalTat: {
        label: string;
        helper: string;
        value: number | null;
        year: number;
    };
    history: HistoryEntry[] | null;
};

const fieldOrder = [
    'suspects',
    'tested',
    'positive',
    'negative',
    'started_treatment',
    'follow_up',
    'median_tat_days',
] as const;

export default function TodayRegister(props: Props) {
    return <RegisterForm key={props.reportedOn} {...props} />;
}

function serverCounts(dailyReturn: Counts): CountState {
    return {
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
    };
}

function RegisterForm(props: Props) {
    const { t } = useEntryI18n();
    const server = serverCounts(props.return);
    const countsRef = useRef<CountState>(server);
    const savingRef = useRef(false);
    const [counts, setCounts] = useState<CountState>(() => {
        const draft = readReturnDraft(props.teamSlug, props.reportedOn);

        if (!draft || draft.baseUpdatedAt !== props.returnUpdatedAt) {
            return server;
        }

        return { ...server, ...draft.counts };
    });
    const [showOther, setShowOther] = useState(
        props.reportedOn !== props.todayDate &&
            props.reportedOn !== props.yesterdayDate,
    );
    const [showSaved, setShowSaved] = useState(false);
    const [savedSnapshot, setSavedSnapshot] = useState<CountState | null>(null);
    const [syncError, setSyncError] = useState('');
    const [saving, setSaving] = useState(false);
    countsRef.current = counts;
    const waiting = useSyncExternalStore(
        subscribeReturnQueue,
        () => queuedReturn(props.teamSlug, props.reportedOn) !== null,
        () => false,
    );
    const dirty = fieldOrder.some((field) => counts[field] !== server[field]);
    const leave = useUnsavedChanges(
        dirty && !showSaved,
        t('leave_unsaved_return'),
    );

    useEffect(() => {
        writeReturnDraft(props.teamSlug, props.reportedOn, {
            baseUpdatedAt: props.returnUpdatedAt,
            counts,
        });
    }, [counts, props.reportedOn, props.returnUpdatedAt, props.teamSlug]);

    useEffect(() => {
        const onFlash = (event: Event) => {
            const flash = (event as CustomEvent).detail?.flash as
                | { savedReturn?: boolean }
                | undefined;

            if (flash?.savedReturn) {
                setSavedSnapshot(countsRef.current);
                setShowSaved(true);
                clearReturnDraft(props.teamSlug, props.reportedOn);
                removeQueuedReturn(props.teamSlug, props.reportedOn);
            }
        };
        const onSynced = (event: Event) => {
            const detail = (event as CustomEvent<QueuedReturn>).detail;

            if (
                detail.teamSlug === props.teamSlug &&
                detail.reportedOn === props.reportedOn
            ) {
                setSavedSnapshot(countsFromPayload(detail.payload));
                setShowSaved(true);
                setSyncError('');
            }
        };
        const onSyncError = (event: Event) => {
            const detail = (
                event as CustomEvent<{
                    teamSlug: string;
                    reportedOn: string;
                    message: string;
                }>
            ).detail;

            if (
                detail.teamSlug === props.teamSlug &&
                detail.reportedOn === props.reportedOn
            ) {
                setSyncError(detail.message || t('sync_failed'));
            }
        };

        const removeFlash = router.on('flash', onFlash);
        window.addEventListener('tbai:returns-synced', onSynced);
        window.addEventListener('tbai:return-sync-error', onSyncError);

        return () => {
            removeFlash();
            window.removeEventListener('tbai:returns-synced', onSynced);
            window.removeEventListener('tbai:return-sync-error', onSyncError);
        };
    }, [props.reportedOn, props.teamSlug, t]);

    const tested = Number(counts.tested);
    const positive = Number(counts.positive);
    const negative = Number(counts.negative);
    const positivity =
        Number.isFinite(tested) && tested > 0 && Number.isFinite(positive)
            ? positive / tested
            : null;
    const testedSumError =
        counts.positive !== '' &&
        counts.negative !== '' &&
        counts.tested !== '' &&
        Number.isFinite(positive) &&
        Number.isFinite(negative) &&
        Number.isFinite(tested) &&
        positive + negative !== tested
            ? t('tested_mismatch', {
                  sum: positive + negative,
                  tested,
              })
            : undefined;

    const changeDate = (value: string) => {
        router.get(
            today.url(props.teamSlug, { query: { reported_on: value } }),
            {},
            { preserveScroll: true },
        );
    };

    const payload = (): Record<string, string> => ({
        reported_on: props.reportedOn,
        ...counts,
    });

    const queueCurrent = () => {
        queueReturn({
            teamSlug: props.teamSlug,
            reportedOn: props.reportedOn,
            url: update.url(props.teamSlug),
            payload: payload(),
        });
        setSyncError('');
    };

    const fieldLabels: Record<string, string> = {
        suspects: t('field_suspects'),
        tested: t('field_tested'),
        positive: t('field_positive'),
        negative: t('field_negative'),
        started_treatment: t('field_started_treatment'),
        follow_up: t('field_follow_up'),
        median_tat_days: t('field_median_tat_days'),
    };

    const countFields: Array<{
        name: keyof CountState;
        label: string;
        decimal?: boolean;
    }> = [
        { name: 'suspects', label: t('suspects') },
        { name: 'tested', label: t('tested') },
        { name: 'positive', label: t('positive') },
        { name: 'negative', label: t('negative') },
        { name: 'started_treatment', label: t('started_treatment') },
        { name: 'follow_up', label: t('follow_up') },
        { name: 'median_tat_days', label: t('median_tat'), decimal: true },
    ];

    return (
        <>
            <Head title={t('register_title')} />
            <EntryScreen
                title={t('register_title')}
                facilityName={props.facility.name}
                dateLabel={props.reportedOnLabel}
                action={
                    props.canSave && !showSaved ? (
                        <Button
                            type="submit"
                            form="daily-return"
                            data-entry-field=""
                            data-test="save-return"
                            className="h-12 w-full text-base"
                            disabled={saving}
                        >
                            {t('save_return')}
                        </Button>
                    ) : null
                }
            >
                {showSaved && savedSnapshot ? (
                    <SavedReturn
                        snapshot={savedSnapshot}
                        labels={fieldLabels}
                        onEdit={() => setShowSaved(false)}
                    />
                ) : (
                    <Form
                        {...update.form(props.teamSlug)}
                        id="daily-return"
                        className="flex flex-col gap-4"
                        options={{ preserveScroll: true }}
                        disableWhileProcessing
                        onBefore={() => {
                            if (savingRef.current) {
                                return false;
                            }

                            if (testedSumError) {
                                scrollToField('tested');
                                return false;
                            }

                            if (!navigator.onLine) {
                                queueCurrent();
                                return false;
                            }

                            savingRef.current = true;
                            setSaving(true);
                            leave.allowNextLeave();
                        }}
                        onNetworkError={() => {
                            queueCurrent();
                            savingRef.current = false;
                            setSaving(false);
                            leave.resetLeave();
                            return false;
                        }}
                        onSuccess={() => {
                            setSavedSnapshot(counts);
                            setShowSaved(true);
                            clearReturnDraft(props.teamSlug, props.reportedOn);
                            removeQueuedReturn(
                                props.teamSlug,
                                props.reportedOn,
                            );
                        }}
                        onError={(errors) => {
                            leave.resetLeave();
                            const first = Object.keys(errors)[0];

                            if (first) {
                                scrollToField(first);
                            }
                        }}
                        onFinish={() => {
                            savingRef.current = false;
                            setSaving(false);
                            leave.resetLeave();
                        }}
                    >
                        {({ errors, processing }) => (
                            <>
                                <section className="rounded-xl border bg-card p-4">
                                    <div className="grid gap-2">
                                        <Label className="text-base">
                                            {t('choose_date')}
                                        </Label>
                                        <div className="flex flex-wrap gap-2">
                                            <DateChip
                                                pressed={
                                                    props.reportedOn ===
                                                    props.todayDate
                                                }
                                                testId="date-today"
                                                onClick={() =>
                                                    changeDate(props.todayDate)
                                                }
                                            >
                                                {t('today')}
                                            </DateChip>
                                            <DateChip
                                                pressed={
                                                    props.reportedOn ===
                                                    props.yesterdayDate
                                                }
                                                testId="date-yesterday"
                                                onClick={() =>
                                                    changeDate(
                                                        props.yesterdayDate,
                                                    )
                                                }
                                            >
                                                {t('yesterday')}
                                            </DateChip>
                                            {props.canChooseDate ? (
                                                <DateChip
                                                    pressed={showOther}
                                                    testId="date-other"
                                                    onClick={() =>
                                                        setShowOther(true)
                                                    }
                                                >
                                                    {t('other_date')}
                                                </DateChip>
                                            ) : null}
                                        </div>
                                        {props.canChooseDate && showOther ? (
                                            <input
                                                id="reported_on_picker"
                                                type="date"
                                                data-entry-field=""
                                                data-test="date-picker"
                                                max={props.latestDate}
                                                min={
                                                    props.earliestDate ??
                                                    undefined
                                                }
                                                value={props.reportedOn}
                                                aria-label={t('choose_date')}
                                                onChange={(event) =>
                                                    changeDate(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-12 w-full rounded-md border border-input bg-transparent px-3 text-base focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none"
                                            />
                                        ) : null}
                                        <input
                                            type="hidden"
                                            name="reported_on"
                                            value={props.reportedOn}
                                        />
                                        <FieldError
                                            message={errors.reported_on}
                                        />
                                    </div>
                                </section>

                                <FacilitySummaryCard
                                    name={props.facility.name}
                                    sector={props.facility.sector}
                                    city={props.facility.city}
                                />

                                {props.lastUpdated ? (
                                    <p
                                        className="text-base text-foreground"
                                        data-test="last-updated"
                                    >
                                        {props.lastUpdated.line}
                                    </p>
                                ) : null}

                                {waiting ? (
                                    <p
                                        className="flex items-start gap-2 rounded-xl border bg-card p-4 text-base text-foreground"
                                        data-test="waiting-to-sync"
                                    >
                                        <CloudOff
                                            aria-hidden="true"
                                            className="mt-0.5 size-5 shrink-0"
                                        />
                                        <span>{t('waiting_to_sync')}</span>
                                    </p>
                                ) : null}

                                {syncError ? (
                                    <FieldError message={syncError} />
                                ) : null}

                                <section className="flex flex-col gap-5 rounded-xl border bg-card p-4">
                                    <p
                                        className="inline-flex w-fit items-center rounded-full bg-muted px-3 py-2 text-base font-medium text-foreground"
                                        data-test="positivity"
                                        aria-live="polite"
                                    >
                                        {positivity === null
                                            ? t('positivity_unavailable')
                                            : t('positivity_value', {
                                                  value: (
                                                      positivity * 100
                                                  ).toFixed(1),
                                              })}
                                    </p>

                                    {countFields.map((field, index) => (
                                        <CountStepper
                                            key={field.name}
                                            id={field.name}
                                            name={field.name}
                                            label={field.label}
                                            value={counts[field.name]}
                                            decimal={field.decimal}
                                            required={!field.decimal}
                                            disabled={
                                                !props.canSave ||
                                                processing ||
                                                saving
                                            }
                                            enterKeyHint={
                                                index === countFields.length - 1
                                                    ? 'done'
                                                    : 'next'
                                            }
                                            hint={
                                                field.name === 'suspects'
                                                    ? t(
                                                          props.isToday
                                                              ? 'suspects_today'
                                                              : 'suspects_date',
                                                          {
                                                              count: props.screeningSuspects,
                                                          },
                                                      )
                                                    : undefined
                                            }
                                            error={
                                                field.name === 'tested'
                                                    ? (testedSumError ??
                                                      errors.tested)
                                                    : errors[field.name]
                                            }
                                            onValueChange={(value) =>
                                                setCounts((current) => ({
                                                    ...current,
                                                    [field.name]: value,
                                                }))
                                            }
                                        />
                                    ))}
                                </section>

                                <section className="space-y-2 rounded-xl border bg-card p-4 text-base">
                                    <h2 className="font-medium">
                                        {t('provisional_heading', {
                                            year: props.provisionalTat.year,
                                        })}
                                    </h2>
                                    <p>
                                        {props.provisionalTat.label}:{' '}
                                        {props.provisionalTat.value === null
                                            ? t('not_enough_returns')
                                            : props.provisionalTat.value.toFixed(
                                                  2,
                                              )}
                                    </p>
                                    <p data-test="turnaround-helper">
                                        {props.provisionalTat.helper}
                                    </p>
                                </section>

                                {props.history !== null ? (
                                    <section
                                        className="space-y-3 rounded-xl border bg-card p-4"
                                        data-test="return-history"
                                    >
                                        <h2 className="text-base font-medium">
                                            {t('history')}
                                        </h2>
                                        {props.history.length === 0 ? (
                                            <p className="text-base">
                                                {t('no_updates')}
                                            </p>
                                        ) : (
                                            <ol className="space-y-3 text-base">
                                                {props.history.map((entry) => (
                                                    <li
                                                        key={`${entry.changedAt}-${entry.changedBy}`}
                                                    >
                                                        <p className="font-medium">
                                                            {entry.changedBy}
                                                        </p>
                                                        <p>{entry.changedAt}</p>
                                                        <ul className="mt-2 space-y-1">
                                                            {entry.changes.map(
                                                                (change) => (
                                                                    <li
                                                                        key={
                                                                            change.field
                                                                        }
                                                                    >
                                                                        {fieldLabels[
                                                                            change
                                                                                .field
                                                                        ] ??
                                                                            change.field}
                                                                        :{' '}
                                                                        {formatValue(
                                                                            change.from,
                                                                            t(
                                                                                'empty',
                                                                            ),
                                                                        )}{' '}
                                                                        {t(
                                                                            'change_to',
                                                                        )}{' '}
                                                                        {formatValue(
                                                                            change.to,
                                                                            t(
                                                                                'empty',
                                                                            ),
                                                                        )}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </li>
                                                ))}
                                            </ol>
                                        )}
                                    </section>
                                ) : null}
                            </>
                        )}
                    </Form>
                )}
            </EntryScreen>
        </>
    );
}

function countsFromPayload(payload: Record<string, string>): CountState {
    return {
        suspects: payload.suspects ?? '',
        tested: payload.tested ?? '',
        positive: payload.positive ?? '',
        negative: payload.negative ?? '',
        started_treatment: payload.started_treatment ?? '',
        follow_up: payload.follow_up ?? '',
        median_tat_days: payload.median_tat_days ?? '',
    };
}

function SavedReturn({
    snapshot,
    labels,
    onEdit,
}: {
    snapshot: CountState;
    labels: Record<string, string>;
    onEdit: () => void;
}) {
    const { t } = useEntryI18n();

    return (
        <section
            className="space-y-4 rounded-xl border bg-card p-4"
            data-test="return-saved"
        >
            <h2 className="text-base font-semibold">{t('return_saved')}</h2>
            <dl className="space-y-2 text-base">
                {fieldOrder.map((field) => (
                    <div key={field} className="flex justify-between gap-4">
                        <dt>{labels[field]}</dt>
                        <dd className="font-medium">
                            {snapshot[field] === ''
                                ? t('empty')
                                : snapshot[field]}
                        </dd>
                    </div>
                ))}
            </dl>
            <Button
                type="button"
                className="h-12 w-full text-base"
                onClick={onEdit}
            >
                {t('edit')}
            </Button>
        </section>
    );
}

function DateChip({
    pressed,
    testId,
    onClick,
    children,
}: {
    pressed: boolean;
    testId: string;
    onClick: () => void;
    children: string;
}) {
    return (
        <button
            type="button"
            data-test={testId}
            aria-pressed={pressed}
            onClick={onClick}
            className={cn(
                'inline-flex h-12 items-center justify-center rounded-full border px-4 text-base font-medium text-foreground',
                'focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:ring-offset-2 focus-visible:outline-none',
                pressed
                    ? 'border-[#14294c] bg-muted'
                    : 'border-input bg-background',
            )}
        >
            {children}
        </button>
    );
}

function formatValue(value: number | null, empty: string): string {
    return value === null ? empty : String(value);
}

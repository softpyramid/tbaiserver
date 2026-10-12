import { Head, router } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import EntryScreen from '@/components/entry-screen';
import {
    SuspectDraftNotice,
    SuspectPrivacyNote,
    SuspectUnavailable,
} from '@/components/suspect-notices';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEntryI18n } from '@/lib/i18n';
import {
    postSuspect,
    SuspectApiError,
    type SuspectProgressResponse,
    type SuspectQuestion,
} from '@/lib/suspect-api';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string };

type Props = {
    facilityName: string;
    dateLabel: string;
    teamSlug: string;
    unavailable: string | null;
    draftNotice: string | null;
    ruleSetVersion: string | null;
    profile: { ageBands: Option[]; sexes: Option[] };
    endpoints: { start: string; assessments: string };
    indexUrl: string;
};

function ChoiceButtons({
    name,
    options,
    value,
    disabled,
    onChoose,
}: {
    name: string;
    options: Option[];
    value: string | null;
    disabled: boolean;
    onChoose: (value: string) => void;
}) {
    return (
        <div
            role="group"
            aria-label={name}
            className="grid grid-cols-1 gap-2 sm:grid-cols-2"
        >
            {options.map((option) => {
                const chosen = value === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={chosen}
                        disabled={disabled}
                        data-test={`suspect-option-${option.value}`}
                        onClick={() => onChoose(option.value)}
                        className={cn(
                            'min-h-14 rounded-xl border px-4 text-left text-base font-medium',
                            'focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none disabled:opacity-60',
                            chosen
                                ? 'border-[#14294c] bg-[#14294c] text-white dark:border-sky-300 dark:bg-sky-300 dark:text-slate-950'
                                : 'bg-card hover:bg-accent',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

export default function SuspectNew({
    facilityName,
    dateLabel,
    teamSlug,
    unavailable,
    draftNotice,
    profile,
    endpoints,
    indexUrl,
}: Props) {
    const { t } = useEntryI18n();
    const [patientName, setPatientName] = useState('');
    const [registerNumber, setRegisterNumber] = useState('');
    const [ageBand, setAgeBand] = useState<string | null>(null);
    const [sex, setSex] = useState<string | null>(null);
    const [state, setState] = useState<SuspectProgressResponse | null>(null);
    const [history, setHistory] = useState<SuspectQuestion[]>([]);
    const [revisit, setRevisit] = useState<SuspectQuestion | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const assessmentUrl = (id: string, action: string) =>
        `${endpoints.assessments}/${id}/${action}`;

    const run = async (work: () => Promise<void>) => {
        setBusy(true);
        setError(null);

        try {
            await work();
        } catch (caught) {
            setError(
                caught instanceof SuspectApiError
                    ? caught.message
                    : t('suspect_error'),
            );
        } finally {
            setBusy(false);
        }
    };

    const start = () =>
        run(async () => {
            const response = await postSuspect<SuspectProgressResponse>(
                endpoints.start,
                {
                    facility: teamSlug,
                    patient_name: patientName.trim(),
                    register_number: registerNumber.trim(),
                    age_band: ageBand,
                    sex,
                },
                t('suspect_error'),
            );
            setState(response);
        });

    const question = revisit ?? state?.next_questions[0] ?? null;
    const answered = new Map(
        (state?.responses ?? []).map((row) => [row.key, row.value]),
    );

    const answer = (target: SuspectQuestion, value: string) =>
        run(async () => {
            if (!state) {
                return;
            }

            const response = await postSuspect<SuspectProgressResponse>(
                assessmentUrl(state.assessment_id, 'answers'),
                { answers: { [target.key]: value } },
                t('suspect_error'),
            );
            const removed = new Set(response.removed_answers);
            setHistory((current) => [
                ...current.filter(
                    (item) => item.key !== target.key && !removed.has(item.key),
                ),
                target,
            ]);
            setRevisit(null);
            setState(response);
        });

    const back = () => {
        if (!question) {
            setRevisit(history[history.length - 1] ?? null);

            return;
        }

        const index = history.findIndex((item) => item.key === question.key);
        const previous =
            index === -1 ? history[history.length - 1] : history[index - 1];

        if (previous) {
            setRevisit(previous);
        }
    };

    const complete = () =>
        run(async () => {
            if (!state) {
                return;
            }

            await postSuspect(
                assessmentUrl(state.assessment_id, 'complete'),
                {},
                t('suspect_error'),
            );
            router.visit(`${indexUrl}/${state.assessment_id}`);
        });

    const canGoBack =
        state !== null &&
        history.length > 0 &&
        (question === null ||
            history.findIndex((item) => item.key === question.key) !== 0);
    const progress = state?.progress ?? { answered: 0, total: 0 };
    const canStart =
        patientName.trim() !== '' &&
        registerNumber.trim() !== '' &&
        ageBand !== null &&
        sex !== null;

    return (
        <>
            <Head title={t('suspect_new_title')} />
            <EntryScreen
                title={t('suspect_new_title')}
                facilityName={facilityName}
                dateLabel={dateLabel}
            >
                {unavailable ? (
                    <SuspectUnavailable text={unavailable} />
                ) : (
                    <>
                        <SuspectDraftNotice
                            text={draftNotice ?? t('suspect_score_label')}
                        />
                        <SuspectPrivacyNote />

                        {state === null ? (
                            <section
                                className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                                data-test="suspect-profile"
                            >
                                <h2 className="text-base font-semibold">
                                    {t('suspect_patient')}
                                </h2>
                                <label className="flex flex-col gap-2">
                                    <span className="text-base font-medium">
                                        {t('suspect_patient_name')}
                                    </span>
                                    <Input
                                        value={patientName}
                                        onChange={(event) =>
                                            setPatientName(event.target.value)
                                        }
                                        required
                                        maxLength={120}
                                        autoComplete="off"
                                        disabled={busy}
                                        className="h-12 text-base"
                                        data-test="suspect-patient-name"
                                    />
                                </label>
                                <label className="flex flex-col gap-2">
                                    <span className="text-base font-medium">
                                        {t('suspect_register_number')}
                                    </span>
                                    <Input
                                        value={registerNumber}
                                        onChange={(event) =>
                                            setRegisterNumber(
                                                event.target.value,
                                            )
                                        }
                                        required
                                        maxLength={50}
                                        autoComplete="off"
                                        disabled={busy}
                                        className="h-12 text-base"
                                        data-test="suspect-register-number"
                                    />
                                </label>
                                <h2 className="text-base font-semibold">
                                    {t('suspect_section_profile')}
                                </h2>
                                <div className="flex flex-col gap-2">
                                    <p className="text-base font-medium">
                                        {t('suspect_age_band')}
                                    </p>
                                    <ChoiceButtons
                                        name={t('suspect_age_band')}
                                        options={profile.ageBands}
                                        value={ageBand}
                                        disabled={busy}
                                        onChoose={setAgeBand}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <p className="text-base font-medium">
                                        {t('suspect_sex')}
                                    </p>
                                    <ChoiceButtons
                                        name={t('suspect_sex')}
                                        options={profile.sexes}
                                        value={sex}
                                        disabled={busy}
                                        onChoose={setSex}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    className="h-12 w-full text-base"
                                    disabled={busy || !canStart}
                                    onClick={() => void start()}
                                    data-test="suspect-start"
                                >
                                    {busy
                                        ? t('suspect_saving')
                                        : t('suspect_start')}
                                </Button>
                            </section>
                        ) : (
                            <>
                                <div className="flex flex-col gap-1">
                                    <p
                                        className="text-sm text-muted-foreground"
                                        aria-live="polite"
                                    >
                                        {t('suspect_progress', {
                                            answered: progress.answered,
                                            total: progress.total,
                                        })}
                                    </p>
                                    <div
                                        role="progressbar"
                                        aria-valuemin={0}
                                        aria-valuemax={progress.total}
                                        aria-valuenow={progress.answered}
                                        aria-label={t('suspect_progress', {
                                            answered: progress.answered,
                                            total: progress.total,
                                        })}
                                        className="h-2 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            className="h-full bg-[#14294c] dark:bg-sky-300"
                                            style={{
                                                width: `${progress.total === 0 ? 0 : Math.round((progress.answered / progress.total) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                </div>

                                {state.alerts.includes(
                                    'HEMOPTYSIS_CLINICAL_REVIEW',
                                ) ? (
                                    <p
                                        role="alert"
                                        className="flex items-start gap-2 rounded-xl border-l-4 border-l-red-600 bg-red-50 p-3 text-base text-foreground dark:bg-red-950/40"
                                        data-test="suspect-live-alert"
                                    >
                                        <TriangleAlert
                                            aria-hidden="true"
                                            className="mt-1 size-4 shrink-0 text-red-700 dark:text-red-400"
                                        />
                                        {t(
                                            'suspect_alert_HEMOPTYSIS_CLINICAL_REVIEW',
                                        )}
                                    </p>
                                ) : null}

                                {state.testing_criterion_met ? (
                                    <p
                                        role="status"
                                        className="flex items-start gap-2 rounded-xl border-l-4 border-l-red-600 bg-red-50 p-3 text-base text-foreground dark:bg-red-950/40"
                                    >
                                        <TriangleAlert
                                            aria-hidden="true"
                                            className="mt-1 size-4 shrink-0 text-red-700 dark:text-red-400"
                                        />
                                        {t('suspect_testing_criterion_met')}
                                    </p>
                                ) : null}

                                {question ? (
                                    <section
                                        className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                                        data-test="suspect-question"
                                        aria-labelledby={`q-${question.key}`}
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            {question.section_label}
                                        </p>
                                        <h2
                                            id={`q-${question.key}`}
                                            className="text-lg font-semibold"
                                        >
                                            {question.prompt}
                                        </h2>
                                        {question.why ? (
                                            <p className="text-sm text-muted-foreground">
                                                {question.why}
                                            </p>
                                        ) : null}
                                        {question.help ? (
                                            <p className="text-sm">
                                                {question.help}
                                            </p>
                                        ) : null}
                                        <ChoiceButtons
                                            name={question.prompt}
                                            options={question.options.map(
                                                (value) => ({
                                                    value,
                                                    label:
                                                        question.option_labels[
                                                            value
                                                        ] ?? value,
                                                }),
                                            )}
                                            value={
                                                answered.get(question.key) ??
                                                null
                                            }
                                            disabled={busy}
                                            onChoose={(value) =>
                                                void answer(question, value)
                                            }
                                        />
                                    </section>
                                ) : (
                                    <p className="rounded-xl border bg-card p-4 text-base">
                                        {t('suspect_all_answered')}
                                    </p>
                                )}

                                <div className="flex flex-col gap-2">
                                    {question === null ? (
                                        <Button
                                            type="button"
                                            className="h-12 w-full text-base"
                                            disabled={busy}
                                            onClick={() => void complete()}
                                            data-test="suspect-complete"
                                        >
                                            {t('suspect_finish')}
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-12 w-full text-base"
                                        disabled={busy || !canGoBack}
                                        onClick={back}
                                        data-test="suspect-back"
                                    >
                                        <ArrowLeft aria-hidden="true" />
                                        {t('suspect_back')}
                                    </Button>
                                    {question !== null ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="min-h-12 w-full text-base whitespace-normal"
                                            disabled={busy}
                                            onClick={() => void complete()}
                                        >
                                            {t('suspect_finish_now')}
                                        </Button>
                                    ) : null}
                                </div>
                            </>
                        )}

                        {error ? (
                            <p
                                role="alert"
                                className="rounded-xl border border-red-600 p-3 text-base text-red-800 dark:text-red-300"
                            >
                                {error}
                            </p>
                        ) : null}
                    </>
                )}
            </EntryScreen>
        </>
    );
}

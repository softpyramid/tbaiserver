import { Form, Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import EntryScreen from '@/components/entry-screen';
import FieldError from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { scrollToField } from '@/lib/entry-fields';
import { useEntryI18n } from '@/lib/i18n';
import {
    clearJson,
    readJson,
    screeningDraftKey,
    writeJson,
} from '@/lib/return-draft';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { cn } from '@/lib/utils';
import { store } from '@/routes/screening';

type Question = {
    key: string;
    label: string;
};

type Confirmation = {
    score: number;
    isSuspect: boolean;
    message: string;
};

type Props = {
    facilityName: string;
    dateLabel: string;
    teamSlug: string;
    notice: string | null;
    questions: Question[];
    confirmation: Confirmation | null;
};

export default function ScreeningNew({
    facilityName,
    dateLabel,
    teamSlug,
    notice,
    questions,
    confirmation,
}: Props) {
    const { t } = useEntryI18n();
    const [dismissed, setDismissed] = useState(false);
    const showingSaved = confirmation !== null && !dismissed;
    const [answers, setAnswers] = useState<Record<string, string>>(() => {
        return (
            readJson<Record<string, string>>(screeningDraftKey(teamSlug)) ?? {}
        );
    });
    const [saving, setSaving] = useState(false);
    const dirty =
        !notice &&
        !showingSaved &&
        questions.some(
            (question) =>
                answers[question.key] === 'yes' ||
                answers[question.key] === 'no',
        );
    const leave = useUnsavedChanges(dirty, t('leave_unsaved_screening'));

    useEffect(() => {
        if (notice || questions.length === 0) {
            return;
        }

        writeJson(screeningDraftKey(teamSlug), answers);
    }, [answers, notice, questions.length, teamSlug]);

    return (
        <>
            <Head title={t('screening_title')} />
            <EntryScreen
                title={t('screening_title')}
                facilityName={facilityName}
                dateLabel={dateLabel}
                action={
                    notice || showingSaved ? null : (
                        <Button
                            type="submit"
                            form="screening-form"
                            data-test="save-screening"
                            className="h-12 w-full text-base"
                            disabled={saving}
                        >
                            {t('save_screening')}
                        </Button>
                    )
                }
            >
                {showingSaved && confirmation ? (
                    <section
                        className="space-y-4 rounded-xl border bg-card p-4"
                        data-test="screening-confirmation"
                    >
                        <p className="text-base text-foreground">
                            {confirmation.message}
                        </p>
                        <Button
                            type="button"
                            className="h-12 w-full text-base"
                            onClick={() => {
                                setDismissed(true);
                                setAnswers({});
                                clearJson(screeningDraftKey(teamSlug));
                            }}
                        >
                            {t('edit')}
                        </Button>
                    </section>
                ) : null}

                {notice ? (
                    <p className="rounded-xl border bg-card p-4 text-base text-foreground">
                        {notice}
                    </p>
                ) : showingSaved ? null : (
                    <Form
                        {...store.form(teamSlug)}
                        id="screening-form"
                        className="flex flex-col gap-4"
                        disableWhileProcessing
                        onBefore={() => {
                            leave.allowNextLeave();
                            setSaving(true);
                        }}
                        onSuccess={() => {
                            clearJson(screeningDraftKey(teamSlug));
                        }}
                        onError={(errors) => {
                            leave.resetLeave();
                            const first = Object.keys(errors)[0];

                            if (first) {
                                scrollToField(first.replaceAll('.', '-'));
                            }
                        }}
                        onFinish={() => {
                            setSaving(false);
                            leave.resetLeave();
                        }}
                    >
                        {({ errors }) => (
                            <>
                                {questions.map((question) => (
                                    <fieldset
                                        key={question.key}
                                        className="grid gap-2 rounded-xl border bg-card p-4"
                                    >
                                        <legend className="text-base font-medium">
                                            {question.label}
                                        </legend>
                                        {(['yes', 'no'] as const).map(
                                            (value) => (
                                                <label
                                                    key={value}
                                                    className={cn(
                                                        'flex min-h-12 items-center gap-3 rounded-lg border px-4 text-base',
                                                        'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#14294c]',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        className="size-5"
                                                        name={`answers[${question.key}]`}
                                                        value={value}
                                                        checked={
                                                            answers[
                                                                question.key
                                                            ] === value
                                                        }
                                                        required
                                                        onChange={() =>
                                                            setAnswers(
                                                                (current) => ({
                                                                    ...current,
                                                                    [question.key]:
                                                                        value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    {t(value)}
                                                </label>
                                            ),
                                        )}
                                        <FieldError
                                            message={
                                                errors[
                                                    `answers.${question.key}`
                                                ]
                                            }
                                        />
                                    </fieldset>
                                ))}
                                <FieldError message={errors.answers} />
                            </>
                        )}
                    </Form>
                )}
            </EntryScreen>
        </>
    );
}

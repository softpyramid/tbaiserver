import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import FieldError from '@/components/field-error';
import PageShell, { type ShellTab } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useEntryI18n } from '@/lib/i18n';

type Thresholds = {
    high: number;
    medium: number;
    low: number;
    refer: number;
};

type RuleSet = {
    id: number;
    version: string;
    status: string;
    statusLabel: string;
    engineVersion: string;
    published: boolean;
    publishedAt: string | null;
    clonedFrom: string | null;
    createdBy: string | null;
    submittedAt: string | null;
    approvedBy: string | null;
    approvedAt: string | null;
    signoffReference: string | null;
    approvalNote: string | null;
    editable: boolean;
    thresholds: Thresholds;
};

type Question = {
    id: number;
    key: string;
    section: string;
    prompt: string;
    options: string;
    askWhen: string;
};

type ScoreItem = {
    id: number;
    component: string;
    key: string;
    label: string;
    condition: string;
    points: number;
};

type ClinicalRule = {
    id: number;
    code: string;
    label: string;
    effect: string;
    condition: string | null;
    conditionItem: string | null;
    nextAction: string;
    reference: string | null;
    configured: boolean;
    editable: boolean;
};

type RiskPattern = {
    id: number;
    code: string;
    reason: string;
    condition: string;
    active: boolean;
};

type Props = {
    tabs: ShellTab[];
    ruleSet: RuleSet;
    questions: Question[];
    scoreItems: ScoreItem[];
    clinicalRules: ClinicalRule[];
    riskPatterns: RiskPattern[];
    criterionOptions: { value: string; label: string }[];
    actions: {
        clone: boolean;
        submit: boolean;
        approve: boolean;
        publish: boolean;
        retire: boolean;
    };
    urls: Record<
        | 'index'
        | 'update'
        | 'clone'
        | 'submit'
        | 'approve'
        | 'publish'
        | 'retire',
        string
    >;
    status: string | null;
};

type Criterion = {
    condition_item: string;
    reference: string;
    configured: boolean;
};

const cellClass = 'border-b px-3 py-2 text-left align-top text-sm';
const headClass =
    'border-b px-3 py-2 text-left text-xs font-semibold text-muted-foreground uppercase';

export default function SuspectRulesShow({
    tabs,
    ruleSet,
    questions,
    scoreItems,
    clinicalRules,
    riskPatterns,
    criterionOptions,
    actions,
    urls,
    status,
}: Props) {
    const { t } = useEntryI18n();
    const editable = ruleSet.editable;
    const [thresholds, setThresholds] = useState(ruleSet.thresholds);
    const [points, setPoints] = useState<Record<number, number>>(() =>
        Object.fromEntries(scoreItems.map((item) => [item.id, item.points])),
    );
    const [criteria, setCriteria] = useState<Record<number, Criterion>>(() =>
        Object.fromEntries(
            clinicalRules
                .filter((rule) => rule.editable)
                .map((rule) => [
                    rule.id,
                    {
                        condition_item: rule.conditionItem ?? '',
                        reference: rule.reference ?? '',
                        configured: rule.configured,
                    },
                ]),
        ),
    );
    const [patterns, setPatterns] = useState<Record<number, boolean>>(() =>
        Object.fromEntries(riskPatterns.map((row) => [row.id, row.active])),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [approving, setApproving] = useState(false);
    const [signoff, setSignoff] = useState('');
    const [note, setNote] = useState('');

    const options = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onError: (next: Record<string, string>) => setErrors(next),
        onSuccess: () => setErrors({}),
    };

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.patch(
            urls.update,
            {
                high_threshold: thresholds.high,
                medium_threshold: thresholds.medium,
                low_threshold: thresholds.low,
                refer_threshold: thresholds.refer,
                items: points,
                criteria,
                patterns,
            },
            options,
        );
    }

    function act(url: string) {
        router.post(url, {}, options);
    }

    function approve(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.post(
            urls.approve,
            { clinical_signoff_reference: signoff, approval_note: note },
            {
                ...options,
                onSuccess: () => {
                    setErrors({});
                    setApproving(false);
                },
            },
        );
    }

    const lifecycle = [
        ruleSet.clonedFrom
            ? t('suspect_cloned_from', { version: ruleSet.clonedFrom })
            : null,
        t('suspect_engine_version', { version: ruleSet.engineVersion }),
        ruleSet.submittedAt
            ? t('suspect_submitted_at', { date: ruleSet.submittedAt })
            : null,
        ruleSet.approvedBy && ruleSet.approvedAt
            ? t('suspect_approved_by_at', {
                  name: ruleSet.approvedBy,
                  date: ruleSet.approvedAt,
              })
            : null,
        ruleSet.signoffReference
            ? `${t('suspect_signoff_reference')}: ${ruleSet.signoffReference}`
            : null,
        ruleSet.approvalNote
            ? `${t('suspect_approval_note')}: ${ruleSet.approvalNote}`
            : null,
        ruleSet.publishedAt
            ? t('suspect_published_at', { date: ruleSet.publishedAt })
            : null,
    ].filter((line): line is string => line !== null);

    return (
        <PageShell
            title={`${t('nav_suspect_rules')}: ${ruleSet.version}`}
            description={t('suspect_rules_description')}
            tabs={tabs}
            eyebrow={
                ruleSet.published
                    ? `${ruleSet.statusLabel}, ${t('suspect_status_published')}`
                    : ruleSet.statusLabel
            }
            crumbs={
                <Link
                    href={urls.index}
                    className="inline-flex w-fit items-center gap-1 text-sm underline-offset-4 hover:underline"
                >
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav_suspect_rule_sets')}
                </Link>
            }
            action={
                <div
                    className="flex flex-wrap gap-2"
                    data-test="suspect-rule-actions"
                >
                    {actions.clone ? (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => act(urls.clone)}
                        >
                            {t('suspect_clone')}
                        </Button>
                    ) : null}
                    {actions.submit ? (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => act(urls.submit)}
                        >
                            {t('suspect_submit')}
                        </Button>
                    ) : null}
                    {actions.approve ? (
                        <Button
                            disabled={busy}
                            onClick={() => setApproving(true)}
                        >
                            {t('suspect_approve')}
                        </Button>
                    ) : null}
                    {actions.publish ? (
                        <Button
                            disabled={busy}
                            onClick={() => act(urls.publish)}
                        >
                            {t('suspect_publish')}
                        </Button>
                    ) : null}
                    {actions.retire ? (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => act(urls.retire)}
                        >
                            {t('suspect_retire')}
                        </Button>
                    ) : null}
                </div>
            }
        >
            {status ? (
                <p
                    role="status"
                    className="rounded-lg border bg-card p-3 text-sm"
                >
                    {status}
                </p>
            ) : null}
            <FieldError message={errors.status} />

            {editable ? null : (
                <p className="flex items-start gap-2 rounded-lg border bg-card p-3 text-sm">
                    <Lock
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0"
                    />
                    {t('suspect_read_only')}
                </p>
            )}

            <section className="rounded-lg border bg-card p-4">
                <h2 className="mb-2 text-base font-semibold">
                    {t('suspect_lifecycle')}
                </h2>
                <ul className="space-y-1 text-sm">
                    {lifecycle.map((line) => (
                        <li key={line}>{line}</li>
                    ))}
                </ul>
            </section>

            <form className="flex flex-col gap-5" onSubmit={save}>
                <section className="rounded-lg border bg-card p-4">
                    <h2 className="mb-3 text-base font-semibold">
                        {t('suspect_thresholds')}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {(
                            [
                                [
                                    'high',
                                    'high_threshold',
                                    t('suspect_threshold_high'),
                                ],
                                [
                                    'medium',
                                    'medium_threshold',
                                    t('suspect_threshold_medium'),
                                ],
                                [
                                    'low',
                                    'low_threshold',
                                    t('suspect_threshold_low'),
                                ],
                                [
                                    'refer',
                                    'refer_threshold',
                                    t('suspect_threshold_refer'),
                                ],
                            ] as const
                        ).map(([key, field, label]) => (
                            <label
                                key={key}
                                className="flex flex-col gap-1 text-sm"
                            >
                                <span>{label}</span>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={thresholds[key]}
                                    disabled={!editable}
                                    onChange={(event) =>
                                        setThresholds((current) => ({
                                            ...current,
                                            [key]: Number(event.target.value),
                                        }))
                                    }
                                />
                                <FieldError message={errors[field]} />
                            </label>
                        ))}
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground">
                        {t('suspect_score_label')}
                    </p>
                </section>

                <section className="overflow-x-auto rounded-lg border bg-card">
                    <h2 className="p-4 pb-2 text-base font-semibold">
                        {t('suspect_score_items')}
                    </h2>
                    <table className="w-full min-w-[40rem]">
                        <thead>
                            <tr>
                                <th className={headClass}>S/E/H</th>
                                <th className={headClass}>
                                    {t('suspect_score_items')}
                                </th>
                                <th className={headClass}>
                                    {t('suspect_condition')}
                                </th>
                                <th className={headClass}>
                                    {t('suspect_points')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {scoreItems.map((item) => (
                                <tr key={item.id}>
                                    <td className={cellClass}>
                                        {item.component}
                                    </td>
                                    <td className={cellClass}>{item.label}</td>
                                    <td
                                        className={`${cellClass} font-mono text-xs`}
                                    >
                                        {item.condition}
                                    </td>
                                    <td className={cellClass}>
                                        {editable ? (
                                            <Input
                                                type="number"
                                                min={0}
                                                max={100}
                                                className="w-24"
                                                aria-label={`${t('suspect_points')}: ${item.label}`}
                                                value={points[item.id]}
                                                onChange={(event) =>
                                                    setPoints((current) => ({
                                                        ...current,
                                                        [item.id]: Number(
                                                            event.target.value,
                                                        ),
                                                    }))
                                                }
                                            />
                                        ) : (
                                            <span className="font-semibold tabular-nums">
                                                {item.points}
                                            </span>
                                        )}
                                        <FieldError
                                            message={errors[`items.${item.id}`]}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="flex flex-col gap-3 rounded-lg border bg-card p-4">
                    <h2 className="text-base font-semibold">
                        {t('suspect_clinical_rules')}
                    </h2>
                    {clinicalRules.map((rule) => {
                        const draft = criteria[rule.id];

                        return (
                            <div
                                key={rule.id}
                                className="flex flex-col gap-2 rounded-md border p-3 text-sm"
                            >
                                <p className="font-semibold">
                                    {rule.label}{' '}
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {rule.code}
                                    </span>
                                </p>
                                <p>
                                    {t('suspect_effect')}:{' '}
                                    {t(`suspect_effect_${rule.effect}`)}.{' '}
                                    {t('suspect_next_action')}:{' '}
                                    {rule.nextAction}
                                </p>
                                {rule.editable && draft ? (
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <label className="flex flex-col gap-1">
                                            <span>
                                                {t(
                                                    'suspect_criterion_condition',
                                                )}
                                            </span>
                                            <select
                                                className="h-9 rounded-md border bg-background px-2"
                                                value={draft.condition_item}
                                                onChange={(event) =>
                                                    setCriteria((current) => ({
                                                        ...current,
                                                        [rule.id]: {
                                                            ...draft,
                                                            condition_item:
                                                                event.target
                                                                    .value,
                                                        },
                                                    }))
                                                }
                                            >
                                                <option value="">
                                                    {t(
                                                        'suspect_criterion_none',
                                                    )}
                                                </option>
                                                {criterionOptions.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <FieldError
                                                message={
                                                    errors[
                                                        `criteria.${rule.id}.condition_item`
                                                    ]
                                                }
                                            />
                                        </label>
                                        <label className="flex flex-col gap-1">
                                            <span>
                                                {t(
                                                    'suspect_criterion_reference',
                                                )}
                                            </span>
                                            <Input
                                                value={draft.reference}
                                                onChange={(event) =>
                                                    setCriteria((current) => ({
                                                        ...current,
                                                        [rule.id]: {
                                                            ...draft,
                                                            reference:
                                                                event.target
                                                                    .value,
                                                        },
                                                    }))
                                                }
                                            />
                                        </label>
                                        <label className="flex items-center gap-2 self-end pb-2">
                                            <input
                                                type="checkbox"
                                                className="size-4"
                                                checked={draft.configured}
                                                onChange={(event) =>
                                                    setCriteria((current) => ({
                                                        ...current,
                                                        [rule.id]: {
                                                            ...draft,
                                                            configured:
                                                                event.target
                                                                    .checked,
                                                        },
                                                    }))
                                                }
                                            />
                                            {t('suspect_configured')}
                                        </label>
                                    </div>
                                ) : (
                                    <p>
                                        {t('suspect_condition')}:{' '}
                                        <span className="font-mono text-xs">
                                            {rule.condition ??
                                                t('suspect_criterion_none')}
                                        </span>
                                        {'. '}
                                        {rule.configured
                                            ? t('suspect_configured')
                                            : t('suspect_not_configured')}
                                        {rule.reference
                                            ? `. ${t('suspect_criterion_reference')}: ${rule.reference}`
                                            : null}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </section>

                <section className="flex flex-col gap-2 rounded-lg border bg-card p-4">
                    <h2 className="text-base font-semibold">
                        {t('suspect_risk_patterns')}
                    </h2>
                    {riskPatterns.map((pattern) => (
                        <label
                            key={pattern.id}
                            className="flex items-start gap-3 rounded-md border p-3 text-sm"
                        >
                            <input
                                type="checkbox"
                                className="mt-0.5 size-4"
                                disabled={!editable}
                                checked={patterns[pattern.id] ?? false}
                                onChange={(event) =>
                                    setPatterns((current) => ({
                                        ...current,
                                        [pattern.id]: event.target.checked,
                                    }))
                                }
                            />
                            <span className="flex flex-col gap-1">
                                <span className="font-semibold">
                                    {pattern.reason}{' '}
                                    <span className="font-normal">
                                        (
                                        {patterns[pattern.id]
                                            ? t('suspect_active')
                                            : t('suspect_inactive')}
                                        )
                                    </span>
                                </span>
                                <span className="font-mono text-xs text-muted-foreground">
                                    {pattern.code}: {pattern.condition}
                                </span>
                            </span>
                        </label>
                    ))}
                </section>

                {editable ? (
                    <div>
                        <Button
                            type="submit"
                            disabled={busy}
                            data-test="suspect-save-draft"
                        >
                            {t('suspect_save_draft')}
                        </Button>
                    </div>
                ) : null}
            </form>

            <section className="overflow-x-auto rounded-lg border bg-card">
                <h2 className="p-4 pb-2 text-base font-semibold">
                    {t('suspect_questions')}
                </h2>
                <table className="w-full min-w-[40rem]">
                    <thead>
                        <tr>
                            <th className={headClass}>
                                {t('suspect_section')}
                            </th>
                            <th className={headClass}>
                                {t('suspect_questions')}
                            </th>
                            <th className={headClass}>
                                {t('suspect_options')}
                            </th>
                            <th className={headClass}>
                                {t('suspect_ask_when')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {questions.map((question) => (
                            <tr key={question.id}>
                                <td className={cellClass}>
                                    {question.section}
                                </td>
                                <td className={cellClass}>
                                    {question.prompt}
                                    <span className="block font-mono text-xs text-muted-foreground">
                                        {question.key}
                                    </span>
                                </td>
                                <td className={cellClass}>
                                    {question.options}
                                </td>
                                <td
                                    className={`${cellClass} font-mono text-xs`}
                                >
                                    {question.askWhen}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <Dialog open={approving} onOpenChange={setApproving}>
                <DialogContent>
                    <form className="flex flex-col gap-4" onSubmit={approve}>
                        <DialogHeader>
                            <DialogTitle>
                                {t('suspect_approve_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('suspect_approve_note')}
                            </DialogDescription>
                        </DialogHeader>
                        <label className="flex flex-col gap-1 text-sm">
                            <span>{t('suspect_signoff_reference')}</span>
                            <Input
                                required
                                value={signoff}
                                onChange={(event) =>
                                    setSignoff(event.target.value)
                                }
                            />
                            <FieldError
                                message={errors.clinical_signoff_reference}
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-sm">
                            <span>{t('suspect_approval_note')}</span>
                            <textarea
                                className="min-h-20 rounded-md border bg-background p-2"
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                            />
                        </label>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setApproving(false)}
                            >
                                {t('suspect_cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={busy || signoff.trim() === ''}
                            >
                                {t('suspect_approve')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

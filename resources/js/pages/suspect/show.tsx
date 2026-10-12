import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, TriangleAlert } from 'lucide-react';
import EntryScreen from '@/components/entry-screen';
import {
    SuspectDraftNotice,
    SuspectReferFlag,
} from '@/components/suspect-notices';
import { Button } from '@/components/ui/button';
import { useEntryI18n } from '@/lib/i18n';
import type { SuspectScores } from '@/lib/suspect-api';

type Result = {
    assessment_id: string;
    status: 'in_progress' | 'completed';
    rule_set_version: string;
    rule_set_status: string;
    score_label: string;
    patient_name: string | null;
    register_number: string | null;
    scores: SuspectScores;
    unknown_items: string[];
    clinical_rule_status: {
        status: string;
        testing_criterion_met: boolean;
        rules: string[];
    };
    alerts: string[];
    risk_patterns?: { code: string; reason: string }[];
    priority?: string | null;
    refer_for_testing?: boolean;
    recommended_next_action?: string | null;
    reason_codes?: string[];
};

type Props = {
    facilityName: string;
    dateLabel: string;
    teamSlug: string;
    result: Result;
    ruleSet: {
        version: string;
        status: string;
        statusLabel: string;
        published: boolean;
    };
    questionLabels: Record<string, string>;
    itemLabels: Record<string, string>;
    priorityLabel: string | null;
    ageBandLabel: string;
    sexLabel: string;
    completedAt: string | null;
    indexUrl: string;
    newUrl: string | null;
};

export default function SuspectShow({
    facilityName,
    dateLabel,
    result,
    ruleSet,
    questionLabels,
    priorityLabel,
    ageBandLabel,
    sexLabel,
    completedAt,
    indexUrl,
    newUrl,
}: Props) {
    const { t } = useEntryI18n();
    const completed = result.status === 'completed';
    const components: [string, number][] = [
        [t('suspect_component_symptom'), result.scores.symptom],
        [t('suspect_component_exposure'), result.scores.exposure],
        [t('suspect_component_history'), result.scores.history],
        [t('suspect_component_raw'), result.scores.raw],
    ];

    return (
        <>
            <Head title={t('suspect_result_title')} />
            <EntryScreen
                title={t('suspect_result_title')}
                facilityName={facilityName}
                dateLabel={completedAt ?? dateLabel}
            >
                {ruleSet.published ? null : (
                    <SuspectDraftNotice text={t('suspect_draft_banner')} />
                )}

                {result.alerts.length > 0 ? (
                    <section
                        aria-labelledby="suspect-alerts"
                        className="flex flex-col gap-2"
                        data-test="suspect-alerts"
                    >
                        <h2 id="suspect-alerts" className="sr-only">
                            {t('suspect_alerts')}
                        </h2>
                        {result.alerts.map((code) => (
                            <p
                                key={code}
                                role="alert"
                                className="flex items-start gap-2 rounded-xl border-l-4 border-l-red-600 bg-red-50 p-3 text-base font-medium text-foreground dark:bg-red-950/40"
                            >
                                <TriangleAlert
                                    aria-hidden="true"
                                    className="mt-1 size-4 shrink-0 text-red-700 dark:text-red-400"
                                />
                                {t(`suspect_alert_${code}`)}
                            </p>
                        ))}
                    </section>
                ) : null}

                <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
                    {result.patient_name || result.register_number ? (
                        <dl
                            className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-base"
                            data-test="suspect-patient"
                        >
                            <dt className="text-muted-foreground">
                                {t('suspect_patient_name')}
                            </dt>
                            <dd className="font-semibold">
                                {result.patient_name}
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('suspect_register_number')}
                            </dt>
                            <dd className="font-semibold">
                                {result.register_number}
                            </dd>
                        </dl>
                    ) : null}
                    <p className="text-sm text-muted-foreground">
                        {t('suspect_age_band')}: {ageBandLabel} ·{' '}
                        {t('suspect_sex')}: {sexLabel}
                    </p>
                    <div>
                        <p
                            className="text-4xl font-semibold tabular-nums"
                            data-test="suspect-score"
                        >
                            {result.scores.score}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {result.score_label}
                        </p>
                    </div>
                    {completed ? (
                        <>
                            <SuspectReferFlag
                                refer={result.refer_for_testing === true}
                                size="lg"
                            />
                            {priorityLabel ? (
                                <p className="text-base">
                                    {t('suspect_priority')}:{' '}
                                    <span className="font-semibold">
                                        {priorityLabel}
                                    </span>
                                </p>
                            ) : null}
                        </>
                    ) : (
                        <p className="text-base text-muted-foreground">
                            {t('suspect_in_progress')}
                        </p>
                    )}
                </section>

                {result.clinical_rule_status.testing_criterion_met ? (
                    <p className="flex items-start gap-2 rounded-xl border-l-4 border-l-red-600 bg-red-50 p-3 text-base dark:bg-red-950/40">
                        <TriangleAlert
                            aria-hidden="true"
                            className="mt-1 size-4 shrink-0 text-red-700 dark:text-red-400"
                        />
                        {t('suspect_testing_criterion_met')}
                    </p>
                ) : result.clinical_rule_status.status === 'not_configured' ? (
                    <p className="rounded-xl border bg-card p-3 text-sm text-muted-foreground">
                        {t('suspect_testing_criterion_not_configured')}
                    </p>
                ) : null}

                {completed && result.recommended_next_action ? (
                    <section className="rounded-xl border bg-card p-4">
                        <h2 className="text-sm text-muted-foreground">
                            {t('suspect_next_action')}
                        </h2>
                        <p
                            className="text-base font-semibold"
                            data-test="suspect-next-action"
                        >
                            {result.recommended_next_action}
                        </p>
                    </section>
                ) : null}

                <section className="rounded-xl border bg-card p-4">
                    <h2 className="mb-2 text-base font-semibold">
                        {t('suspect_breakdown')}
                    </h2>
                    <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-base">
                        {components.map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt>{label}</dt>
                                <dd className="text-right font-semibold tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section className="rounded-xl border bg-card p-4">
                    <h2 className="mb-2 text-base font-semibold">
                        {t('suspect_unknown_items')}
                    </h2>
                    {result.unknown_items.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('suspect_unknown_none')}
                        </p>
                    ) : (
                        <ul
                            className="list-disc pl-5 text-base"
                            data-test="suspect-unknown-items"
                        >
                            {result.unknown_items.map((key) => (
                                <li key={key}>{questionLabels[key] ?? key}</li>
                            ))}
                        </ul>
                    )}
                </section>

                {(result.risk_patterns ?? []).length > 0 ? (
                    <section className="rounded-xl border bg-card p-4">
                        <h2 className="mb-2 text-base font-semibold">
                            {t('suspect_risk_patterns')}
                        </h2>
                        <ul className="list-disc pl-5 text-base">
                            {(result.risk_patterns ?? []).map((pattern) => (
                                <li key={pattern.code}>{pattern.reason}</li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <p className="text-sm text-muted-foreground">
                    {t('suspect_rule_set', {
                        version: ruleSet.version,
                        status: ruleSet.statusLabel,
                    })}
                </p>

                <div className="flex flex-col gap-2">
                    {newUrl ? (
                        <Button asChild className="h-12 w-full text-base">
                            <Link href={newUrl}>
                                <Plus aria-hidden="true" />
                                {t('suspect_new')}
                            </Link>
                        </Button>
                    ) : null}
                    <Button
                        asChild
                        variant="outline"
                        className="h-12 w-full text-base"
                    >
                        <Link href={indexUrl}>
                            <ArrowLeft aria-hidden="true" />
                            {t('suspect_title')}
                        </Link>
                    </Button>
                </div>
            </EntryScreen>
        </>
    );
}

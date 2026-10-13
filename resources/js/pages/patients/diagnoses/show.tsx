import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Microscope, Printer } from 'lucide-react';
import { DiagnosisDemoBanner } from '@/components/diagnosis-demo-banner';
import EntryScreen from '@/components/entry-screen';
import { Button } from '@/components/ui/button';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Finding = { x: number; y: number; w: number; h: number; label: string };

type Props = {
    facilityName: string;
    dateLabel: string;
    title: string;
    banner: string;
    gradeNote: string;
    patient: {
        id: string;
        name: string;
        registerNumber: string;
        genderLabel: string;
        age: number;
    };
    diagnosis: {
        id: string;
        date: string;
        testLabel: string;
        result: 'positive' | 'negative' | 'indeterminate';
        resultLabel: string;
        grade: string | null;
        simulated: boolean;
        imageUrl: string;
        findings: Finding[];
        sourceLabel: string;
        symptoms: string | null;
        notes: string | null;
        uploadedBy: string;
        engineLabel: string;
    };
    backUrl: string;
    newUrl: string;
};

const resultStyles: Record<Props['diagnosis']['result'], string> = {
    positive: 'border-red-700 bg-red-700 text-white',
    negative: 'border-emerald-700 bg-emerald-700 text-white',
    indeterminate: 'border-amber-600 bg-amber-100 text-amber-950',
};

const percent = (value: number) => `${(value * 100).toFixed(2)}%`;

export default function DiagnosisShow({
    facilityName,
    dateLabel,
    title,
    banner,
    gradeNote,
    patient,
    diagnosis,
    backUrl,
}: Props) {
    const { t } = useEntryI18n();
    const patientRows: [string, string][] = [
        [t('diagnosis_patient'), patient.name],
        [t('patients_register_number'), patient.registerNumber],
        [t('patients_age'), String(patient.age)],
        [t('patients_gender'), patient.genderLabel],
    ];
    const testRows: [string, string][] = [
        [t('diagnosis_test'), diagnosis.testLabel],
        [t('diagnosis_image_source'), diagnosis.sourceLabel],
        [t('diagnosis_uploaded_by'), diagnosis.uploadedBy],
        [t('diagnosis_analysed_at'), diagnosis.date],
        [t('diagnosis_engine'), diagnosis.engineLabel],
    ];

    return (
        <>
            <Head title={title} />
            <EntryScreen
                title={title}
                facilityName={facilityName}
                dateLabel={dateLabel}
                headerless
                wide
            >
                <article
                    className="diagnosis-report mx-auto flex w-full max-w-3xl flex-col gap-4"
                    data-test="diagnosis-report"
                >
                    {diagnosis.simulated ? (
                        <DiagnosisDemoBanner text={banner} />
                    ) : null}

                    <header className="flex flex-col gap-1 border-b pb-3">
                        <p className="text-sm text-muted-foreground">
                            {facilityName}
                        </p>
                        <h1 className="flex items-center gap-2 text-xl font-semibold">
                            <Microscope aria-hidden="true" className="size-6" />
                            {title}
                        </h1>
                    </header>

                    <section className="grid gap-4 md:grid-cols-2">
                        <Details rows={patientRows} />
                        <Details rows={testRows} />
                    </section>

                    <section
                        className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"
                        data-test="diagnosis-result"
                    >
                        <span className="text-base font-semibold">
                            {t('diagnosis_result')}
                        </span>
                        <span
                            className={cn(
                                'rounded-md border-2 px-3 py-1 text-base font-bold tracking-wide',
                                resultStyles[diagnosis.result],
                            )}
                        >
                            {diagnosis.resultLabel}
                        </span>
                        {diagnosis.grade ? (
                            <span className="text-base">
                                {t('diagnosis_grade')}:{' '}
                                <span className="font-semibold">
                                    {diagnosis.grade}
                                </span>{' '}
                                <span className="text-sm text-muted-foreground">
                                    ({gradeNote})
                                </span>
                            </span>
                        ) : null}
                    </section>

                    <section className="flex flex-col gap-2">
                        <h2 className="text-base font-semibold">
                            {t('diagnosis_slide')}
                        </h2>
                        <figure className="diagnosis-slide flex flex-col gap-1">
                            <div className="relative mx-auto w-full max-w-md">
                                <img
                                    src={diagnosis.imageUrl}
                                    alt={t('diagnosis_slide_alt')}
                                    className="block w-full rounded-lg border"
                                    data-test="diagnosis-image"
                                />
                                {diagnosis.findings.map((finding, index) => (
                                    <div
                                        key={index}
                                        className="diagnosis-box absolute rounded-sm border-2 border-red-600 shadow-[0_0_0_1px_rgba(255,255,255,0.8)]"
                                        style={{
                                            left: percent(finding.x),
                                            top: percent(finding.y),
                                            width: percent(finding.w),
                                            height: percent(finding.h),
                                        }}
                                        aria-hidden="true"
                                    >
                                        <span className="absolute -top-5 left-0 rounded-sm bg-red-600 px-1 text-xs leading-4 font-semibold whitespace-nowrap text-white">
                                            {finding.label} {index + 1}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            {diagnosis.simulated ? (
                                <figcaption className="text-center text-sm text-muted-foreground">
                                    {t('diagnosis_highlights_note')}
                                </figcaption>
                            ) : null}
                        </figure>
                    </section>

                    {diagnosis.symptoms || diagnosis.notes ? (
                        <section className="flex flex-col gap-2 rounded-xl border bg-card p-4">
                            {diagnosis.symptoms ? (
                                <TextBlock
                                    label={t('diagnosis_symptoms')}
                                    text={diagnosis.symptoms}
                                />
                            ) : null}
                            {diagnosis.notes ? (
                                <TextBlock
                                    label={t('diagnosis_notes')}
                                    text={diagnosis.notes}
                                />
                            ) : null}
                        </section>
                    ) : null}

                    {diagnosis.simulated ? (
                        <footer
                            className="hidden border-t pt-2 text-sm font-semibold print:block"
                            data-test="diagnosis-print-footer"
                        >
                            {banner}
                        </footer>
                    ) : null}

                    <div className="flex flex-col gap-2 sm:flex-row print:hidden">
                        <Button
                            type="button"
                            className="h-12 text-base sm:flex-1"
                            onClick={() => window.print()}
                            data-test="diagnosis-print"
                        >
                            <Printer aria-hidden="true" />
                            {t('diagnosis_print')}
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="h-12 text-base sm:flex-1"
                        >
                            <Link href={backUrl}>
                                <ArrowLeft aria-hidden="true" />
                                {t('diagnosis_back')}
                            </Link>
                        </Button>
                    </div>
                </article>
            </EntryScreen>
        </>
    );
}

function Details({ rows }: { rows: [string, string][] }) {
    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-xl border bg-card p-4 text-base">
            {rows.map(([label, value]) => (
                <div key={label} className="contents">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="font-medium break-words">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function TextBlock({ label, text }: { label: string; text: string }) {
    return (
        <div className="flex flex-col gap-1">
            <h3 className="text-base font-semibold">{label}</h3>
            <p className="text-base whitespace-pre-line">{text}</p>
        </div>
    );
}

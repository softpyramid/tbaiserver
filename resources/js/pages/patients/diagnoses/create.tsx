import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Camera,
    Check,
    Circle,
    ImageUp,
    LoaderCircle,
    Microscope,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DiagnosisDemoBanner } from '@/components/diagnosis-demo-banner';
import EntryScreen from '@/components/entry-screen';
import FieldError from '@/components/field-error';
import { TextAreaField } from '@/components/patient-fields';
import { Button } from '@/components/ui/button';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Sample = { key: string; label: string; url: string };

type Props = {
    facilityName: string;
    dateLabel: string;
    patient: {
        id: string;
        name: string;
        registerNumber: string;
        genderLabel: string;
        age: number;
    };
    testType: string;
    samples: Sample[];
    maxMegabytes: number;
    storeUrl: string;
    backUrl: string;
};

type Source = 'camera' | 'upload' | 'sample';

type FormData = {
    test_type: string;
    image_source: Source | '';
    sample: string;
    image: File | null;
    symptoms: string;
    notes: string;
};

const TOTAL_STEPS = 3;
// Three processing stages: about 4.5 seconds, or about 1 second with reduced motion.
const STAGE_MS = 1500;
const REDUCED_STAGE_MS = 300;

export default function DiagnosisCreate({
    facilityName,
    dateLabel,
    patient,
    testType,
    samples,
    maxMegabytes,
    storeUrl,
    backUrl,
}: Props) {
    const { t } = useEntryI18n();
    const [step, setStep] = useState(1);
    const [preview, setPreview] = useState<string | null>(null);
    const [stage, setStage] = useState<number | null>(null);
    const [failed, setFailed] = useState(false);
    const cameraRef = useRef<HTMLInputElement>(null);
    const deviceRef = useRef<HTMLInputElement>(null);
    const timers = useRef<number[]>([]);
    const target = useRef<string | null>(null);
    const animationDone = useRef(false);
    const form = useForm<FormData>({
        test_type: testType,
        image_source: '',
        sample: '',
        image: null,
        symptoms: '',
        notes: '',
    });
    const processing = stage !== null;
    const stages = [
        t('diagnosis_processing_quality'),
        t('diagnosis_processing_detect'),
        t('diagnosis_processing_report'),
    ];
    const hasImage =
        form.data.image_source === 'sample'
            ? form.data.sample !== ''
            : form.data.image !== null;

    useEffect(() => {
        const pending = timers.current;

        return () => {
            pending.forEach((id) => window.clearTimeout(id));
        };
    }, []);

    useEffect(() => {
        return () => {
            if (preview?.startsWith('blob:')) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    function chooseFile(source: Source, list: FileList | null) {
        const file = list?.[0];

        if (!file) {
            return;
        }

        form.setData((data) => ({
            ...data,
            image_source: source,
            image: file,
            sample: '',
        }));
        form.clearErrors('image', 'sample', 'image_source');
        setPreview(URL.createObjectURL(file));
    }

    function chooseSample(sample: Sample) {
        form.setData((data) => ({
            ...data,
            image_source: 'sample',
            sample: sample.key,
            image: null,
        }));
        form.clearErrors('image', 'sample', 'image_source');
        setPreview(sample.url);
    }

    function finish() {
        if (animationDone.current && target.current !== null) {
            router.visit(target.current);
        }
    }

    function stop() {
        timers.current.forEach((id) => window.clearTimeout(id));
        timers.current = [];
        setStage(null);
    }

    function analyse() {
        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        const ms = reduced ? REDUCED_STAGE_MS : STAGE_MS;

        target.current = null;
        animationDone.current = false;
        setFailed(false);
        setStage(0);
        timers.current = [1, 2, 3].map((next) =>
            window.setTimeout(() => {
                setStage(next);

                if (next === TOTAL_STEPS) {
                    animationDone.current = true;
                    finish();
                }
            }, ms * next),
        );

        form.post(storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const url = (page.flash as Record<string, unknown> | undefined)
                    ?.diagnosisUrl;

                if (typeof url === 'string') {
                    target.current = url;
                    finish();
                } else {
                    stop();
                    setFailed(true);
                }
            },
            onError: (errors) => {
                stop();
                setFailed(true);
                setStep(
                    'image' in errors ||
                        'sample' in errors ||
                        'image_source' in errors
                        ? 2
                        : 3,
                );
            },
        });
    }

    const imageError =
        form.errors.image ?? form.errors.sample ?? form.errors.image_source;

    return (
        <>
            <Head title={t('diagnosis_create_title')} />
            <EntryScreen
                title={t('diagnosis_create_title')}
                facilityName={facilityName}
                dateLabel={dateLabel}
            >
                <DiagnosisDemoBanner text={t('diagnosis_demo_banner')} />

                <p className="text-base" data-test="diagnosis-patient">
                    <span className="font-semibold">{patient.name}</span>
                    {' · '}
                    {patient.registerNumber}
                    {' · '}
                    {patient.genderLabel}, {patient.age}
                </p>

                {processing ? (
                    <Processing stage={stage} stages={stages} />
                ) : (
                    <>
                        <p
                            className="text-sm font-medium text-muted-foreground"
                            aria-live="polite"
                        >
                            {t('diagnosis_steps', {
                                step,
                                total: TOTAL_STEPS,
                            })}
                        </p>

                        {failed ? (
                            <p
                                role="alert"
                                className="rounded-lg border border-red-700 p-3 text-base text-red-800 dark:text-red-200"
                            >
                                {t('diagnosis_processing_failed')}
                            </p>
                        ) : null}

                        {step === 1 ? (
                            <section
                                className="flex flex-col gap-3"
                                aria-labelledby="diagnosis-step-test"
                            >
                                <h2
                                    id="diagnosis-step-test"
                                    className="text-base font-semibold"
                                >
                                    {t('diagnosis_step_test')}
                                </h2>
                                <button
                                    type="button"
                                    aria-pressed="true"
                                    data-test="diagnosis-test-tb"
                                    className="flex items-start gap-3 rounded-xl border-2 border-[#14294c] bg-card p-4 text-left focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none dark:border-sky-300"
                                >
                                    <Microscope
                                        aria-hidden="true"
                                        className="mt-0.5 size-6 shrink-0"
                                    />
                                    <span className="flex flex-col gap-1">
                                        <span className="text-base font-semibold">
                                            {t('diagnosis_test_card_title')}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {t('diagnosis_test_card_body')}
                                        </span>
                                    </span>
                                    <Check
                                        aria-hidden="true"
                                        className="ml-auto size-5 shrink-0"
                                    />
                                </button>
                                <Button
                                    type="button"
                                    className="h-12 w-full text-base"
                                    onClick={() => setStep(2)}
                                >
                                    {t('diagnosis_next')}
                                </Button>
                            </section>
                        ) : null}

                        {step === 2 ? (
                            <section
                                className="flex flex-col gap-3"
                                aria-labelledby="diagnosis-step-image"
                            >
                                <h2
                                    id="diagnosis-step-image"
                                    className="text-base font-semibold"
                                >
                                    {t('diagnosis_step_image')}
                                </h2>
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-12 text-base"
                                        onClick={() =>
                                            cameraRef.current?.click()
                                        }
                                    >
                                        <Camera aria-hidden="true" />
                                        {t('diagnosis_take_photo')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-12 text-base"
                                        onClick={() =>
                                            deviceRef.current?.click()
                                        }
                                    >
                                        <ImageUp aria-hidden="true" />
                                        {t('diagnosis_upload')}
                                    </Button>
                                </div>
                                <input
                                    ref={cameraRef}
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    className="sr-only"
                                    tabIndex={-1}
                                    aria-label={t('diagnosis_take_photo')}
                                    data-test="diagnosis-camera"
                                    onChange={(event) => {
                                        chooseFile(
                                            'camera',
                                            event.currentTarget.files,
                                        );
                                        event.currentTarget.value = '';
                                    }}
                                />
                                <input
                                    ref={deviceRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="sr-only"
                                    tabIndex={-1}
                                    aria-label={t('diagnosis_upload')}
                                    data-test="diagnosis-upload"
                                    onChange={(event) => {
                                        chooseFile(
                                            'upload',
                                            event.currentTarget.files,
                                        );
                                        event.currentTarget.value = '';
                                    }}
                                />
                                <p className="text-sm text-muted-foreground">
                                    {t('diagnosis_image_hint', {
                                        size: maxMegabytes,
                                    })}
                                </p>

                                <h3 className="text-base font-semibold">
                                    {t('diagnosis_samples')}
                                </h3>
                                <div className="grid grid-cols-2 gap-2">
                                    {samples.map((sample) => {
                                        const active =
                                            form.data.image_source ===
                                                'sample' &&
                                            form.data.sample === sample.key;

                                        return (
                                            <button
                                                key={sample.key}
                                                type="button"
                                                aria-pressed={active}
                                                data-test={`diagnosis-sample-${sample.key}`}
                                                onClick={() =>
                                                    chooseSample(sample)
                                                }
                                                className={cn(
                                                    'flex flex-col gap-1 rounded-lg border-2 p-1 text-sm focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none',
                                                    active
                                                        ? 'border-[#14294c] dark:border-sky-300'
                                                        : 'border-border',
                                                )}
                                            >
                                                <img
                                                    src={sample.url}
                                                    alt=""
                                                    className="aspect-[3/4] w-full rounded object-cover"
                                                />
                                                <span className="font-medium">
                                                    {sample.label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>

                                {preview ? (
                                    <figure className="flex flex-col gap-1">
                                        <img
                                            src={preview}
                                            alt={t('diagnosis_preview')}
                                            data-test="diagnosis-preview"
                                            className="max-h-80 w-full rounded-lg border object-contain"
                                        />
                                        <figcaption className="text-sm text-muted-foreground">
                                            {t('diagnosis_preview')}
                                        </figcaption>
                                    </figure>
                                ) : null}
                                <FieldError message={imageError} />

                                <StepButtons
                                    onBack={() => setStep(1)}
                                    onNext={() => setStep(3)}
                                    nextDisabled={!hasImage}
                                />
                            </section>
                        ) : null}

                        {step === 3 ? (
                            <section
                                className="flex flex-col gap-3"
                                aria-labelledby="diagnosis-step-details"
                            >
                                <h2
                                    id="diagnosis-step-details"
                                    className="text-base font-semibold"
                                >
                                    {t('diagnosis_step_details')}
                                </h2>
                                <TextAreaField
                                    id="symptoms"
                                    label={t('diagnosis_symptoms')}
                                    value={form.data.symptoms}
                                    error={form.errors.symptoms}
                                    onChange={(value) =>
                                        form.setData('symptoms', value)
                                    }
                                />
                                <p className="-mt-2 text-sm text-muted-foreground">
                                    {t('diagnosis_symptoms_hint')}
                                </p>
                                <TextAreaField
                                    id="notes"
                                    label={t('diagnosis_notes')}
                                    value={form.data.notes}
                                    error={form.errors.notes}
                                    onChange={(value) =>
                                        form.setData('notes', value)
                                    }
                                />
                                <p className="-mt-2 text-sm text-muted-foreground">
                                    {t('diagnosis_notes_hint')}
                                </p>
                                <Button
                                    type="button"
                                    className="h-12 w-full text-base"
                                    disabled={!hasImage || form.processing}
                                    onClick={analyse}
                                    data-test="diagnosis-analyse"
                                >
                                    <Microscope aria-hidden="true" />
                                    {t('diagnosis_analyse')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-12 w-full text-base"
                                    onClick={() => setStep(2)}
                                >
                                    <ArrowLeft aria-hidden="true" />
                                    {t('diagnosis_previous')}
                                </Button>
                            </section>
                        ) : null}

                        <Button
                            asChild
                            variant="ghost"
                            className="h-11 w-full text-base"
                        >
                            <Link href={backUrl}>{t('diagnosis_cancel')}</Link>
                        </Button>
                    </>
                )}
            </EntryScreen>
        </>
    );
}

function StepButtons({
    onBack,
    onNext,
    nextDisabled,
}: {
    onBack: () => void;
    onNext: () => void;
    nextDisabled: boolean;
}) {
    const { t } = useEntryI18n();

    return (
        <div className="flex gap-2">
            <Button
                type="button"
                variant="outline"
                className="h-12 text-base"
                onClick={onBack}
            >
                <ArrowLeft aria-hidden="true" />
                {t('diagnosis_previous')}
            </Button>
            <Button
                type="button"
                className="h-12 flex-1 text-base"
                disabled={nextDisabled}
                onClick={onNext}
            >
                {t('diagnosis_next')}
            </Button>
        </div>
    );
}

function Processing({
    stage,
    stages,
}: {
    stage: number | null;
    stages: string[];
}) {
    const { t } = useEntryI18n();
    const current = stage ?? 0;
    const percent = Math.round((current / stages.length) * 100);

    return (
        <section
            className="flex flex-col gap-4 rounded-xl border bg-card p-4"
            aria-labelledby="diagnosis-processing"
            data-test="diagnosis-processing"
        >
            <h2 id="diagnosis-processing" className="text-base font-semibold">
                {t('diagnosis_processing')}
            </h2>
            <div
                role="progressbar"
                aria-labelledby="diagnosis-processing"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                className="h-2 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-[#14294c] transition-[width] duration-700 ease-out motion-reduce:transition-none dark:bg-sky-300"
                    style={{ width: `${Math.max(percent, 4)}%` }}
                />
            </div>
            <ol className="flex flex-col gap-3" aria-live="polite">
                {stages.map((label, index) => {
                    const done = index < current;
                    const active = index === current;

                    return (
                        <li
                            key={label}
                            className={cn(
                                'flex items-center gap-3 text-base',
                                !done && !active && 'text-muted-foreground',
                            )}
                        >
                            {done ? (
                                <Check
                                    aria-hidden="true"
                                    className="size-5 text-emerald-700 dark:text-emerald-300"
                                />
                            ) : active ? (
                                <LoaderCircle
                                    aria-hidden="true"
                                    className="size-5 motion-safe:animate-spin"
                                />
                            ) : (
                                <Circle aria-hidden="true" className="size-5" />
                            )}
                            <span className={active ? 'font-semibold' : ''}>
                                {label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

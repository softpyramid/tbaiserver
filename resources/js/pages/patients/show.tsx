import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Microscope, Pencil, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import EntryScreen from '@/components/entry-screen';
import {
    SelectField,
    TextAreaField,
    TextField,
    type Option,
} from '@/components/patient-fields';
import { SuspectReferFlag } from '@/components/suspect-notices';
import { Button } from '@/components/ui/button';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Visit = {
    id: number;
    month: number;
    scheduledDate: string;
    attendedDate: string | null;
    attendedLabel: string | null;
    sputumResult: string | null;
    sputumLabel: string | null;
    notes: string | null;
    status: 'attended' | 'missed' | 'due' | 'upcoming' | 'cancelled';
    statusLabel: string;
    editable: boolean;
    updateUrl: string;
};

type Treatment = {
    id: number;
    active: boolean;
    startDate: string;
    startDateIso: string;
    regimenLabel: string;
    registrationTypeLabel: string;
    siteLabel: string;
    tb03Number: string | null;
    notes: string | null;
    outcomeLabel: string | null;
    outcomeDate: string | null;
    outcomeUrl: string;
    visits: Visit[];
};

type Screening = {
    id: string;
    date: string;
    completed: boolean;
    score: number | null;
    priorityLabel: string | null;
    refer: boolean;
    href: string;
};

type Diagnosis = {
    id: string;
    date: string;
    testLabel: string;
    result: 'positive' | 'negative' | 'indeterminate';
    resultLabel: string;
    grade: string | null;
    simulated: boolean;
    thumbnailUrl: string | null;
    href: string;
};

type Props = {
    facilityName: string;
    dateLabel: string;
    today: string;
    patient: {
        id: string;
        name: string;
        cnic: string | null;
        phone: string | null;
        genderLabel: string;
        age: number;
        registerNumber: string;
        informedAt: string | null;
    };
    screenings: Screening[];
    diagnoses: Diagnosis[];
    newDiagnosisUrl: string | null;
    activeTreatment: Treatment | null;
    pastTreatments: Treatment[];
    options: {
        regimens: Option[];
        registrationTypes: Option[];
        sites: Option[];
        outcomes: Option[];
        sputumResults: Option[];
    };
    startTreatmentUrl: string;
    editUrl: string;
    indexUrl: string;
};

const statusStyles: Record<Visit['status'], string> = {
    attended: 'border-emerald-700 text-emerald-900 dark:text-emerald-200',
    missed: 'border-red-700 bg-red-700 text-white',
    due: 'border-amber-600 text-amber-950 dark:text-amber-100',
    upcoming: 'border-border text-foreground',
    cancelled: 'border-border text-muted-foreground line-through',
};

export default function PatientShow({
    facilityName,
    dateLabel,
    today,
    patient,
    screenings,
    diagnoses,
    newDiagnosisUrl,
    activeTreatment,
    pastTreatments,
    options,
    startTreatmentUrl,
    editUrl,
    indexUrl,
}: Props) {
    const { t } = useEntryI18n();
    const details: [string, string][] = [
        [t('patients_register_number'), patient.registerNumber],
        [t('patients_gender'), patient.genderLabel],
        [t('patients_age'), String(patient.age)],
        [t('patients_cnic'), patient.cnic ?? t('patients_not_recorded')],
        [t('patients_phone'), patient.phone ?? t('patients_not_recorded')],
    ];

    return (
        <>
            <Head title={t('patients_record_title')} />
            <EntryScreen
                title={t('patients_record_title')}
                facilityName={facilityName}
                dateLabel={dateLabel}
            >
                <section
                    className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                    data-test="patient-details"
                >
                    <h2 className="text-lg font-semibold">{patient.name}</h2>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-base">
                        {details.map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium break-words">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {patient.informedAt ? (
                        <p className="text-sm text-muted-foreground">
                            {t('patients_informed_at', {
                                time: patient.informedAt,
                            })}
                        </p>
                    ) : null}
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 w-full text-base"
                    >
                        <Link href={editUrl} data-test="patient-edit">
                            <Pencil aria-hidden="true" />
                            {t('patients_edit')}
                        </Link>
                    </Button>
                </section>

                <section
                    className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                    aria-labelledby="treatment-heading"
                    data-test="patient-treatment"
                >
                    <h2
                        id="treatment-heading"
                        className="text-base font-semibold"
                    >
                        {t('patients_treatment')}
                    </h2>
                    {activeTreatment ? (
                        <>
                            <TreatmentSummary treatment={activeTreatment} />
                            <VisitList
                                visits={activeTreatment.visits}
                                startDate={activeTreatment.startDateIso}
                                today={today}
                                sputumResults={options.sputumResults}
                            />
                            <OutcomeForm
                                treatment={activeTreatment}
                                today={today}
                                outcomes={options.outcomes}
                            />
                        </>
                    ) : (
                        <>
                            <p className="text-base text-muted-foreground">
                                {t('patients_treatment_none')}
                            </p>
                            <StartTreatmentForm
                                url={startTreatmentUrl}
                                today={today}
                                options={options}
                            />
                        </>
                    )}
                </section>

                {pastTreatments.length > 0 ? (
                    <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
                        <h2 className="text-base font-semibold">
                            {t('patients_past_treatments')}
                        </h2>
                        {pastTreatments.map((treatment) => (
                            <div
                                key={treatment.id}
                                className="flex flex-col gap-3 border-t pt-3 first:border-t-0 first:pt-0"
                            >
                                <TreatmentSummary treatment={treatment} />
                                <VisitList
                                    visits={treatment.visits}
                                    startDate={treatment.startDateIso}
                                    today={today}
                                    sputumResults={options.sputumResults}
                                />
                            </div>
                        ))}
                    </section>
                ) : null}

                <section className="flex flex-col gap-2 rounded-xl border bg-card p-4">
                    <h2 className="text-base font-semibold">
                        {t('patients_screenings')}
                    </h2>
                    {screenings.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('patients_screenings_empty')}
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {screenings.map((screening) => (
                                <li key={screening.id}>
                                    <Link
                                        href={screening.href}
                                        className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-base hover:bg-accent focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none"
                                    >
                                        <span className="text-muted-foreground">
                                            {screening.date}
                                        </span>
                                        {screening.completed ? (
                                            <>
                                                <span className="font-semibold">
                                                    {t('column_score')}:{' '}
                                                    {screening.score}
                                                </span>
                                                {screening.priorityLabel ? (
                                                    <span>
                                                        {
                                                            screening.priorityLabel
                                                        }
                                                    </span>
                                                ) : null}
                                                <SuspectReferFlag
                                                    refer={screening.refer}
                                                />
                                            </>
                                        ) : (
                                            <span>
                                                {t('suspect_in_progress')}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {newDiagnosisUrl ? (
                    <DiagnosisList
                        diagnoses={diagnoses}
                        newUrl={newDiagnosisUrl}
                    />
                ) : null}

                <Button
                    asChild
                    variant="outline"
                    className="h-12 w-full text-base"
                >
                    <Link href={indexUrl}>
                        <ArrowLeft aria-hidden="true" />
                        {t('patients_back')}
                    </Link>
                </Button>
            </EntryScreen>
        </>
    );
}

function DiagnosisList({
    diagnoses,
    newUrl,
}: {
    diagnoses: Diagnosis[];
    newUrl: string;
}) {
    const { t } = useEntryI18n();

    return (
        <section
            className="flex flex-col gap-2 rounded-xl border bg-card p-4"
            data-test="patient-diagnoses"
        >
            <h2 className="text-base font-semibold">
                {t('diagnosis_list_title')}
            </h2>
            <Button asChild className="h-12 w-full text-base">
                <Link href={newUrl} data-test="diagnosis-new">
                    <Microscope aria-hidden="true" />
                    {t('diagnosis_new')}
                </Link>
            </Button>
            {diagnoses.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    {t('diagnosis_list_empty')}
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {diagnoses.map((diagnosis) => (
                        <li key={diagnosis.id}>
                            <Link
                                href={diagnosis.href}
                                className="flex items-center gap-3 rounded-lg border p-3 text-base hover:bg-accent focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none"
                            >
                                {diagnosis.thumbnailUrl ? (
                                    <img
                                        src={diagnosis.thumbnailUrl}
                                        alt=""
                                        className="size-12 shrink-0 rounded object-cover"
                                    />
                                ) : null}
                                <span className="flex flex-col">
                                    <span className="font-medium">
                                        {diagnosis.testLabel}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {diagnosis.date}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'ml-auto rounded-md border px-2 py-0.5 text-sm font-semibold',
                                        diagnosis.result === 'positive'
                                            ? 'border-red-700 bg-red-700 text-white'
                                            : 'border-border',
                                    )}
                                >
                                    {diagnosis.resultLabel}
                                    {diagnosis.grade
                                        ? ` · ${diagnosis.grade}`
                                        : ''}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function TreatmentSummary({ treatment }: { treatment: Treatment }) {
    const { t } = useEntryI18n();
    const rows: [string, string][] = [
        [t('patients_regimen'), treatment.regimenLabel],
        [t('patients_registration_type'), treatment.registrationTypeLabel],
        [t('patients_site'), treatment.siteLabel],
    ];

    if (treatment.tb03Number) {
        rows.push([t('patients_tb03_number'), treatment.tb03Number]);
    }

    return (
        <div className="flex flex-col gap-2">
            <p className="text-base font-semibold">
                {t('patients_started_on', { date: treatment.startDate })}
            </p>
            {treatment.outcomeLabel ? (
                <p className="text-base" data-test="treatment-outcome">
                    {t('patients_outcome')}:{' '}
                    <span className="font-semibold">
                        {t('patients_outcome_on', {
                            outcome: treatment.outcomeLabel,
                            date: treatment.outcomeDate ?? '',
                        })}
                    </span>
                </p>
            ) : null}
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-base">
                {rows.map(([label, value]) => (
                    <div key={label} className="contents">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="font-medium">{value}</dd>
                    </div>
                ))}
            </dl>
            {treatment.notes ? (
                <p className="text-base whitespace-pre-line">
                    {treatment.notes}
                </p>
            ) : null}
        </div>
    );
}

function VisitList({
    visits,
    startDate,
    today,
    sputumResults,
}: {
    visits: Visit[];
    startDate: string;
    today: string;
    sputumResults: Option[];
}) {
    const { t } = useEntryI18n();

    return (
        <div className="flex flex-col gap-2">
            <h3 className="text-base font-semibold">{t('patients_visits')}</h3>
            <ol className="flex flex-col gap-2" data-test="treatment-visits">
                {visits.map((visit) => (
                    <VisitItem
                        key={visit.id}
                        visit={visit}
                        startDate={startDate}
                        today={today}
                        sputumResults={sputumResults}
                    />
                ))}
            </ol>
        </div>
    );
}

function VisitItem({
    visit,
    startDate,
    today,
    sputumResults,
}: {
    visit: Visit;
    startDate: string;
    today: string;
    sputumResults: Option[];
}) {
    const { t } = useEntryI18n();
    const [open, setOpen] = useState(false);
    const form = useForm({
        attended_date: visit.attendedDate ?? '',
        sputum_result: visit.sputumResult ?? '',
        notes: visit.notes ?? '',
    });
    const prefix = `visit-${visit.id}`;

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(visit.updateUrl, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <li
            className="flex flex-col gap-2 rounded-lg border p-3"
            data-test={`visit-${visit.month}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-base font-semibold">
                    {t('patients_visit_month', { month: visit.month })}
                </span>
                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-sm font-medium',
                        statusStyles[visit.status],
                    )}
                >
                    {visit.status === 'missed' ? (
                        <TriangleAlert
                            aria-hidden="true"
                            className="size-3.5"
                        />
                    ) : null}
                    {visit.statusLabel}
                </span>
            </div>
            <p className="text-base text-muted-foreground">
                {t('patients_visit_due', { date: visit.scheduledDate })}
                {visit.attendedLabel
                    ? ` · ${t('patients_visit_attended_on', { date: visit.attendedLabel })}`
                    : ''}
            </p>
            {visit.sputumLabel ? (
                <p className="text-base">
                    {t('patients_visit_sputum')}: {visit.sputumLabel}
                </p>
            ) : null}
            {visit.notes ? (
                <p className="text-base whitespace-pre-line">{visit.notes}</p>
            ) : null}
            {visit.editable && !open ? (
                <Button
                    type="button"
                    variant="outline"
                    className="h-11 w-full text-base"
                    aria-expanded={open}
                    onClick={() => setOpen(true)}
                >
                    {t('patients_visit_record')}
                </Button>
            ) : null}
            {visit.editable && open ? (
                <form
                    className="flex flex-col gap-3"
                    onSubmit={submit}
                    data-test="visit-form"
                >
                    <TextField
                        id={`${prefix}-attended`}
                        type="date"
                        min={startDate}
                        max={today}
                        label={t('patients_visit_attended_date')}
                        value={form.data.attended_date}
                        error={form.errors.attended_date}
                        onChange={(value) =>
                            form.setData('attended_date', value)
                        }
                    />
                    <SelectField
                        id={`${prefix}-sputum`}
                        label={t('patients_visit_sputum')}
                        value={form.data.sputum_result}
                        options={sputumResults}
                        placeholder={t('patients_choose')}
                        error={form.errors.sputum_result}
                        onChange={(value) =>
                            form.setData('sputum_result', value)
                        }
                    />
                    <TextAreaField
                        id={`${prefix}-notes`}
                        label={t('patients_notes')}
                        value={form.data.notes}
                        error={form.errors.notes}
                        onChange={(value) => form.setData('notes', value)}
                    />
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            className="h-11 flex-1 text-base"
                            disabled={form.processing}
                        >
                            {t('patients_visit_save')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 text-base"
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                                setOpen(false);
                            }}
                        >
                            {t('patients_cancel')}
                        </Button>
                    </div>
                </form>
            ) : null}
        </li>
    );
}

function StartTreatmentForm({
    url,
    today,
    options,
}: {
    url: string;
    today: string;
    options: Props['options'];
}) {
    const { t } = useEntryI18n();
    const form = useForm({
        start_date: today,
        regimen_category: '',
        registration_type: '',
        site: '',
        tb03_number: '',
        notes: '',
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(url, { preserveScroll: true });
    }

    return (
        <form
            className="flex flex-col gap-3"
            onSubmit={submit}
            data-test="treatment-start-form"
        >
            <h3 className="text-base font-semibold">
                {t('patients_treatment_start')}
            </h3>
            <TextField
                id="start_date"
                type="date"
                max={today}
                label={t('patients_treatment_start_date')}
                value={form.data.start_date}
                error={form.errors.start_date}
                required
                onChange={(value) => form.setData('start_date', value)}
            />
            <SelectField
                id="regimen_category"
                label={t('patients_regimen')}
                value={form.data.regimen_category}
                options={options.regimens}
                placeholder={t('patients_choose')}
                error={form.errors.regimen_category}
                required
                onChange={(value) => form.setData('regimen_category', value)}
            />
            <SelectField
                id="registration_type"
                label={t('patients_registration_type')}
                value={form.data.registration_type}
                options={options.registrationTypes}
                placeholder={t('patients_choose')}
                error={form.errors.registration_type}
                required
                onChange={(value) => form.setData('registration_type', value)}
            />
            <SelectField
                id="site"
                label={t('patients_site')}
                value={form.data.site}
                options={options.sites}
                placeholder={t('patients_choose')}
                error={form.errors.site}
                required
                onChange={(value) => form.setData('site', value)}
            />
            <TextField
                id="tb03_number"
                label={t('patients_tb03_number')}
                value={form.data.tb03_number}
                error={form.errors.tb03_number}
                onChange={(value) => form.setData('tb03_number', value)}
            />
            <TextAreaField
                id="treatment_notes"
                label={t('patients_notes')}
                value={form.data.notes}
                error={form.errors.notes}
                onChange={(value) => form.setData('notes', value)}
            />
            <p className="text-sm text-muted-foreground">
                {t('patients_schedule_note')}
            </p>
            <Button
                type="submit"
                className="h-12 w-full text-base"
                disabled={form.processing}
                data-test="treatment-start"
            >
                {t('patients_treatment_start')}
            </Button>
        </form>
    );
}

function OutcomeForm({
    treatment,
    today,
    outcomes,
}: {
    treatment: Treatment;
    today: string;
    outcomes: Option[];
}) {
    const { t } = useEntryI18n();
    const form = useForm({ outcome: '', outcome_date: today });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(treatment.outcomeUrl, { preserveScroll: true });
    }

    return (
        <form
            className="flex flex-col gap-3 border-t pt-3"
            onSubmit={submit}
            data-test="treatment-outcome-form"
        >
            <h3 className="text-base font-semibold">{t('patients_outcome')}</h3>
            <SelectField
                id="outcome"
                label={t('patients_outcome')}
                value={form.data.outcome}
                options={outcomes}
                placeholder={t('patients_choose')}
                error={form.errors.outcome}
                required
                onChange={(value) => form.setData('outcome', value)}
            />
            <TextField
                id="outcome_date"
                type="date"
                min={treatment.startDateIso}
                max={today}
                label={t('patients_outcome_date')}
                value={form.data.outcome_date}
                error={form.errors.outcome_date}
                required
                onChange={(value) => form.setData('outcome_date', value)}
            />
            <p className="text-sm text-muted-foreground">
                {t('patients_outcome_note')}
            </p>
            <Button
                type="submit"
                variant="outline"
                className="h-12 w-full text-base"
                disabled={form.processing}
            >
                {t('patients_outcome_save')}
            </Button>
        </form>
    );
}

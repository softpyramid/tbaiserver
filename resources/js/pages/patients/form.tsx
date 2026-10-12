import { Head, Link, useForm } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import EntryScreen from '@/components/entry-screen';
import FieldError from '@/components/field-error';
import {
    SelectField,
    TextField,
    type Option,
} from '@/components/patient-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useEntryI18n } from '@/lib/i18n';

type Props = {
    facilityName: string;
    dateLabel: string;
    genders: Option[];
    mode: 'create' | 'edit';
    action: string;
    method: 'post' | 'put';
    backUrl: string;
    screening: string | null;
    patient: {
        name: string;
        cnic: string;
        phone: string;
        gender: string;
        age: string;
        registerNumber: string;
        informedAt: string | null;
    };
};

export default function PatientForm({
    facilityName,
    dateLabel,
    genders,
    mode,
    action,
    method,
    backUrl,
    screening,
    patient,
}: Props) {
    const { t } = useEntryI18n();
    const creating = mode === 'create';
    const title = creating
        ? t('patients_create_title')
        : t('patients_edit_title');
    const form = useForm({
        name: patient.name,
        cnic: patient.cnic,
        phone: patient.phone,
        gender: patient.gender,
        age: patient.age,
        register_number: patient.registerNumber,
        informed_of_data_use: false,
        screening: screening ?? '',
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => {
            const payload: Record<string, string | boolean> = {
                name: data.name,
                cnic: data.cnic,
                phone: data.phone,
                gender: data.gender,
                age: data.age,
                register_number: data.register_number,
            };

            if (creating) {
                payload.informed_of_data_use = data.informed_of_data_use;
                payload.screening = data.screening;
            }

            return payload;
        });
        form.submit(method, action, { preserveScroll: true });
    }

    return (
        <>
            <Head title={title} />
            <EntryScreen
                title={title}
                facilityName={facilityName}
                dateLabel={dateLabel}
            >
                {creating ? (
                    <p
                        className="flex items-start gap-2 rounded-xl border bg-card p-3 text-base text-foreground"
                        data-test="patients-privacy-notice"
                        role="note"
                    >
                        <ShieldAlert
                            aria-hidden="true"
                            className="mt-1 size-4 shrink-0"
                        />
                        <span>{t('patients_privacy_notice')}</span>
                    </p>
                ) : null}

                {screening ? (
                    <p className="rounded-xl border bg-card p-3 text-sm text-muted-foreground">
                        {t('patients_from_screening')}
                    </p>
                ) : null}

                <form
                    className="flex flex-col gap-4"
                    onSubmit={submit}
                    noValidate
                    data-test="patients-form"
                >
                    <TextField
                        id="name"
                        label={t('patients_name')}
                        value={form.data.name}
                        error={form.errors.name}
                        required
                        onChange={(value) => form.setData('name', value)}
                    />
                    <TextField
                        id="register_number"
                        label={t('patients_register_number')}
                        value={form.data.register_number}
                        error={form.errors.register_number}
                        required
                        onChange={(value) =>
                            form.setData('register_number', value)
                        }
                    />
                    <SelectField
                        id="gender"
                        label={t('patients_gender')}
                        value={form.data.gender}
                        options={genders}
                        placeholder={t('patients_gender_choose')}
                        error={form.errors.gender}
                        required
                        onChange={(value) => form.setData('gender', value)}
                    />
                    <TextField
                        id="age"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={120}
                        label={t('patients_age')}
                        value={form.data.age}
                        error={form.errors.age}
                        required
                        onChange={(value) => form.setData('age', value)}
                    />
                    <TextField
                        id="cnic"
                        inputMode="numeric"
                        label={t('patients_cnic')}
                        hint={t('patients_cnic_hint')}
                        value={form.data.cnic}
                        error={form.errors.cnic}
                        onChange={(value) => form.setData('cnic', value)}
                    />
                    <TextField
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        label={t('patients_phone')}
                        hint={t('patients_phone_hint')}
                        value={form.data.phone}
                        error={form.errors.phone}
                        onChange={(value) => form.setData('phone', value)}
                    />

                    {creating ? (
                        <div className="flex flex-col gap-1.5">
                            <div className="flex items-start gap-3 rounded-xl border bg-card p-3">
                                <Checkbox
                                    id="informed_of_data_use"
                                    checked={form.data.informed_of_data_use}
                                    aria-invalid={
                                        form.errors.informed_of_data_use
                                            ? true
                                            : undefined
                                    }
                                    aria-describedby={
                                        form.errors.informed_of_data_use
                                            ? 'informed_of_data_use-error'
                                            : undefined
                                    }
                                    className="mt-1 size-5"
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'informed_of_data_use',
                                            checked === true,
                                        )
                                    }
                                />
                                <label
                                    htmlFor="informed_of_data_use"
                                    className="text-base font-medium"
                                >
                                    {t('patients_informed')}
                                </label>
                            </div>
                            <FieldError
                                id="informed_of_data_use-error"
                                message={form.errors.informed_of_data_use}
                            />
                        </div>
                    ) : patient.informedAt ? (
                        <p className="text-sm text-muted-foreground">
                            {t('patients_informed_at', {
                                time: patient.informedAt,
                            })}
                        </p>
                    ) : null}

                    <div className="flex flex-col gap-2">
                        <Button
                            type="submit"
                            className="h-12 w-full text-base"
                            disabled={form.processing}
                            data-test="patients-save"
                        >
                            {t('patients_save')}
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="h-12 w-full text-base"
                        >
                            <Link href={backUrl}>{t('patients_cancel')}</Link>
                        </Button>
                    </div>
                </form>
            </EntryScreen>
        </>
    );
}

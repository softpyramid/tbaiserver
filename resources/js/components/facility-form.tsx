import { Form, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import EntryScreen from '@/components/entry-screen';
import FacilitySummaryCard from '@/components/facility-summary-card';
import FieldError from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEntryI18n } from '@/lib/i18n';
import {
    clearJson,
    profileDraftKey,
    readJson,
    writeJson,
} from '@/lib/return-draft';
import { cn } from '@/lib/utils';
import { scrollToField } from '@/lib/entry-fields';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { store, update } from '@/routes/facilities';

type Choice = {
    value: string;
    label: string;
};

type CityOption = {
    id: number;
    name: string;
};

type TalukaOption = {
    id: number;
    cityId: number;
    name: string;
};

export type FacilityFormValues = {
    name: string;
    sector: string;
    kind: string;
    cityId: number | null;
    talukaId: number | null;
    code: string;
    active: boolean;
};

type Summary = {
    name: string;
    sector: string;
    city: string;
};

type Props = {
    titleKey: 'profile_title' | 'create_title';
    dateLabel: string;
    teamSlug?: string;
    canEdit?: boolean;
    summary?: Summary;
    savedMessage?: string | null;
    facility?: FacilityFormValues;
    sectors: Choice[];
    kinds: Choice[];
    cities: CityOption[];
    talukas: TalukaOption[];
    belowFields?: ReactNode;
    /** True when a page header above already names the facility and the page. */
    embedded?: boolean;
};

type DraftValues = {
    name: string;
    sector: string;
    kind: string;
    cityId: string;
    talukaId: string;
    code: string;
    active: boolean;
};

const selectClassName =
    'border-input flex h-12 w-full rounded-md border bg-transparent px-3 text-base text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function fromFacility(facility?: FacilityFormValues): DraftValues {
    return {
        name: facility?.name ?? '',
        sector: facility?.sector ?? '',
        kind: facility?.kind ?? '',
        cityId:
            facility?.cityId === null || facility?.cityId === undefined
                ? ''
                : String(facility.cityId),
        talukaId:
            facility?.talukaId === null || facility?.talukaId === undefined
                ? ''
                : String(facility.talukaId),
        code: facility?.code ?? '',
        active: facility?.active ?? true,
    };
}

export default function FacilityForm({
    titleKey,
    dateLabel,
    teamSlug,
    canEdit = true,
    summary,
    savedMessage,
    facility,
    sectors,
    kinds,
    cities,
    talukas,
    belowFields,
    embedded = false,
}: Props) {
    const { t } = useEntryI18n();
    const draftKey = profileDraftKey(teamSlug ?? null);
    const baseline = fromFacility(facility);
    const [values, setValues] = useState<DraftValues>(() => {
        return readJson<DraftValues>(draftKey) ?? baseline;
    });
    const [showSaved, setShowSaved] = useState(Boolean(savedMessage));
    const [saving, setSaving] = useState(false);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );
    const savingRef = useRef(false);
    const dirty =
        canEdit &&
        !showSaved &&
        (Object.keys(baseline) as Array<keyof DraftValues>).some(
            (key) => values[key] !== baseline[key],
        );
    const leave = useUnsavedChanges(dirty, t('leave_unsaved_profile'));

    useEffect(() => {
        if (!canEdit) {
            return;
        }

        writeJson(draftKey, values);
    }, [canEdit, draftKey, values]);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash as
                | { savedProfile?: string }
                | undefined;

            if (flash?.savedProfile) {
                setShowSaved(true);
                clearJson(draftKey);
            }
        });
    }, [draftKey]);

    const talukasForCity = talukas.filter(
        (taluka) => String(taluka.cityId) === values.cityId,
    );
    const headerName = summary?.name || values.name || t(titleKey);

    if (!canEdit && summary) {
        return (
            <EntryScreen
                title={t(titleKey)}
                facilityName={summary.name}
                dateLabel={dateLabel}
                headerless={embedded}
            >
                <FacilitySummaryCard
                    name={summary.name}
                    sector={summary.sector}
                    city={summary.city}
                />
                <p className="text-base text-foreground">
                    {t('read_only_profile')}
                </p>
            </EntryScreen>
        );
    }

    return (
        <EntryScreen
            title={t(titleKey)}
            facilityName={headerName}
            dateLabel={dateLabel}
            headerless={embedded}
            wide={embedded}
            action={
                showSaved || (embedded && !dirty && !saving) ? null : (
                    <Button
                        type="submit"
                        form="facility-form"
                        data-test="save-profile"
                        className="h-12 w-full text-base"
                        disabled={saving}
                    >
                        {teamSlug ? t('save_profile') : t('create_facility')}
                    </Button>
                )
            }
        >
            {showSaved ? (
                <section
                    className="space-y-4 rounded-xl border bg-card p-4"
                    data-test="profile-saved"
                >
                    <h2 className="text-base font-semibold">
                        {savedMessage || t('profile_saved')}
                    </h2>
                    <FacilitySummaryCard
                        name={summary?.name || values.name}
                        sector={
                            summary?.sector ||
                            sectors.find(
                                (sector) => sector.value === values.sector,
                            )?.label ||
                            values.sector
                        }
                        city={
                            summary?.city ||
                            cities.find(
                                (city) => String(city.id) === values.cityId,
                            )?.name ||
                            values.cityId
                        }
                    />
                    <Button
                        type="button"
                        className="h-12 w-full text-base"
                        onClick={() => setShowSaved(false)}
                    >
                        {t('edit')}
                    </Button>
                </section>
            ) : (
                <Form
                    {...(teamSlug ? update.form(teamSlug) : store.form())}
                    id="facility-form"
                    className="grid grid-cols-1 gap-4 lg:grid-cols-2"
                    noValidate
                    disableWhileProcessing
                    onBefore={() => {
                        if (savingRef.current) {
                            return false;
                        }

                        const next: Record<string, string> = {};

                        if (values.name.trim() === '') {
                            next.name = 'The name field is required.';
                        }

                        if (values.sector === '') {
                            next.sector = 'The sector field is required.';
                        }

                        if (values.kind === '') {
                            next.kind = 'The kind field is required.';
                        }

                        if (values.cityId === '') {
                            next.city_id = 'The city id field is required.';
                        }

                        setClientErrors(next);
                        const first = Object.keys(next)[0];

                        if (first) {
                            scrollToField(first);
                            return false;
                        }

                        savingRef.current = true;
                        setSaving(true);
                        leave.allowNextLeave();
                    }}
                    onSuccess={() => {
                        clearJson(draftKey);
                    }}
                    onError={() => {
                        leave.resetLeave();
                    }}
                    onFinish={() => {
                        savingRef.current = false;
                        setSaving(false);
                        leave.resetLeave();
                    }}
                >
                    {({ errors }) => {
                        const message = (key: string) =>
                            clientErrors[key] || errors[key];

                        return (
                            <>
                                {!teamSlug ? (
                                    <p className="text-base text-foreground lg:col-span-2">
                                        {t('create_description')}
                                    </p>
                                ) : null}
                                <div className="grid gap-2 rounded-xl border bg-card p-4">
                                    <Label htmlFor="name" className="text-base">
                                        {t('facility_name')}
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        value={values.name}
                                        aria-required="true"
                                        aria-invalid={Boolean(message('name'))}
                                        aria-describedby={
                                            message('name')
                                                ? 'name-error'
                                                : undefined
                                        }
                                        maxLength={255}
                                        className="h-12 text-base md:text-base"
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                name: event.target.value,
                                            }))
                                        }
                                    />
                                    <FieldError
                                        id="name-error"
                                        message={message('name')}
                                    />
                                </div>

                                <div className="grid gap-2 rounded-xl border bg-card p-4">
                                    <Label
                                        htmlFor="sector"
                                        className="text-base"
                                    >
                                        {t('sector')}
                                    </Label>
                                    <select
                                        id="sector"
                                        name="sector"
                                        className={selectClassName}
                                        value={values.sector}
                                        aria-required="true"
                                        aria-invalid={Boolean(
                                            message('sector'),
                                        )}
                                        aria-describedby={
                                            message('sector')
                                                ? 'sector-error'
                                                : undefined
                                        }
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                sector: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="" disabled>
                                            {t('select_sector')}
                                        </option>
                                        {sectors.map((sector) => (
                                            <option
                                                key={sector.value}
                                                value={sector.value}
                                            >
                                                {sector.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError
                                        id="sector-error"
                                        message={message('sector')}
                                    />
                                </div>

                                <div className="grid gap-2 rounded-xl border bg-card p-4">
                                    <Label htmlFor="kind" className="text-base">
                                        {t('kind')}
                                    </Label>
                                    <select
                                        id="kind"
                                        name="kind"
                                        className={selectClassName}
                                        value={values.kind}
                                        aria-required="true"
                                        aria-invalid={Boolean(message('kind'))}
                                        aria-describedby={
                                            message('kind')
                                                ? 'kind-error'
                                                : undefined
                                        }
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                kind: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="" disabled>
                                            {t('select_kind')}
                                        </option>
                                        {kinds.map((kind) => (
                                            <option
                                                key={kind.value}
                                                value={kind.value}
                                            >
                                                {kind.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError
                                        id="kind-error"
                                        message={message('kind')}
                                    />
                                </div>

                                <div className="grid gap-2 rounded-xl border bg-card p-4">
                                    <Label
                                        htmlFor="city_id"
                                        className="text-base"
                                    >
                                        {t('city')}
                                    </Label>
                                    <select
                                        id="city_id"
                                        name="city_id"
                                        className={selectClassName}
                                        value={values.cityId}
                                        aria-required="true"
                                        aria-invalid={Boolean(
                                            message('city_id'),
                                        )}
                                        aria-describedby={
                                            message('city_id')
                                                ? 'city_id-error'
                                                : undefined
                                        }
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                cityId: event.target.value,
                                                talukaId: '',
                                            }))
                                        }
                                    >
                                        <option value="" disabled>
                                            {t('select_city')}
                                        </option>
                                        {cities.map((city) => (
                                            <option
                                                key={city.id}
                                                value={city.id}
                                            >
                                                {city.name}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError
                                        id="city_id-error"
                                        message={message('city_id')}
                                    />
                                </div>

                                <div className="grid gap-2 rounded-xl border bg-card p-4">
                                    <Label
                                        htmlFor="taluka_id"
                                        className="text-base"
                                    >
                                        {t('taluka')}
                                    </Label>
                                    <select
                                        id="taluka_id"
                                        name="taluka_id"
                                        className={selectClassName}
                                        value={values.talukaId}
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                talukaId: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">{t('none')}</option>
                                        {talukasForCity.map((taluka) => (
                                            <option
                                                key={taluka.id}
                                                value={taluka.id}
                                            >
                                                {taluka.name}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={errors.taluka_id} />
                                </div>

                                <div className="grid gap-2 rounded-xl border bg-card p-4">
                                    <Label htmlFor="code" className="text-base">
                                        {t('programme_code')}
                                    </Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        value={values.code}
                                        maxLength={255}
                                        className="h-12 text-base md:text-base"
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                code: event.target.value,
                                            }))
                                        }
                                    />
                                    <FieldError message={errors.code} />
                                </div>

                                {teamSlug ? (
                                    <input
                                        type="hidden"
                                        name="active"
                                        value={values.active ? '1' : '0'}
                                    />
                                ) : (
                                    <div className="flex min-h-12 items-center gap-3 rounded-xl border bg-card p-4 lg:col-span-2">
                                        <input
                                            id="active"
                                            name="active"
                                            type="checkbox"
                                            value="1"
                                            checked={values.active}
                                            onChange={(event) =>
                                                setValues((current) => ({
                                                    ...current,
                                                    active: event.target
                                                        .checked,
                                                }))
                                            }
                                            className={cn(
                                                'size-5 rounded border border-input',
                                            )}
                                        />
                                        <Label
                                            htmlFor="active"
                                            className="text-base"
                                        >
                                            {t('active')}
                                        </Label>
                                    </div>
                                )}
                                <FieldError message={errors.active} />
                                {belowFields ? (
                                    <div className="lg:col-span-2">
                                        {belowFields}
                                    </div>
                                ) : null}
                            </>
                        );
                    }}
                </Form>
            )}
        </EntryScreen>
    );
}

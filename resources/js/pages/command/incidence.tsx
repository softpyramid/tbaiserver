import { Form } from '@inertiajs/react';
import {
    type CommandFilters,
    type CommandLabels,
    type CommandLink,
    type CommandOption,
    CommandShell,
    type CommandScreenLink,
    type CommandShow,
} from '@/components/command-center';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type TargetRow = {
    id: number;
    provinceId: string;
    province: string;
    year: number;
    value: string;
};

type SeriesPoint = {
    year: number;
    province: string;
    value: string;
};

type Props = {
    title: string;
    scope: string;
    screens: CommandScreenLink[];
    filters: CommandFilters;
    options: {
        provinces: CommandOption[];
        districts: CommandOption[];
        sectors: CommandOption[];
    };
    show: CommandShow;
    labels: CommandLabels;
    links: CommandLink[];
    incidenceLabels: {
        chart: string;
        table: string;
        year: string;
        province: string;
        value: string;
        save: string;
        add: string;
        note: string;
    };
    canEdit: boolean;
    storeUrl: string | null;
    yearOptions: number[];
    targetProvinces: CommandOption[];
    targets: TargetRow[];
    series: SeriesPoint[];
    empty: string | null;
};

export default function CommandIncidence({
    title,
    scope,
    screens,
    filters,
    options,
    show,
    labels,
    links,
    incidenceLabels,
    canEdit,
    storeUrl,
    yearOptions,
    targetProvinces,
    targets,
    series,
    empty,
}: Props) {
    return (
        <CommandShell
            title={title}
            scope={scope}
            screens={screens}
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            <p className="text-sm text-muted-foreground">{incidenceLabels.note}</p>

            {empty ? (
                <p className="text-sm text-muted-foreground" data-test="incidence-empty">
                    {empty}
                </p>
            ) : null}

            {series.length > 0 ? <YearChart heading={incidenceLabels.chart} points={series} /> : null}

            {targets.length > 0 ? (
                <section className="min-w-0" data-test="incidence-table">
                    <h2 className="mb-3 text-base font-medium">{incidenceLabels.table}</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="px-2 py-2 font-medium">{incidenceLabels.province}</th>
                                    <th className="px-2 py-2 font-medium">{incidenceLabels.year}</th>
                                    <th className="px-2 py-2 font-medium">{incidenceLabels.value}</th>
                                    {canEdit ? <th className="px-2 py-2 font-medium">{incidenceLabels.save}</th> : null}
                                </tr>
                            </thead>
                            <tbody>
                                {targets.map((target) => (
                                    <tr key={target.id} className="border-b" data-test={`incidence-row-${target.id}`}>
                                        <td className="px-2 py-2">{target.province}</td>
                                        <td className="px-2 py-2 tabular-nums">{target.year}</td>
                                        <td className="px-2 py-2 tabular-nums">{target.value}</td>
                                        {canEdit && storeUrl ? (
                                            <td className="px-2 py-2">
                                                <EditTarget
                                                    storeUrl={storeUrl}
                                                    target={target}
                                                    saveLabel={incidenceLabels.save}
                                                    valueLabel={incidenceLabels.value}
                                                />
                                            </td>
                                        ) : null}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            ) : null}

            {canEdit && storeUrl && targetProvinces.length > 0 ? (
                <AddTarget
                    storeUrl={storeUrl}
                    yearOptions={yearOptions}
                    provinces={targetProvinces}
                    labels={incidenceLabels}
                />
            ) : null}
        </CommandShell>
    );
}

function YearChart({ heading, points }: { heading: string; points: SeriesPoint[] }) {
    const max = Math.max(
        1,
        ...points.map((point) => {
            const amount = Number(point.value);

            return Number.isFinite(amount) ? amount : 0;
        }),
    );

    return (
        <section className="min-w-0" data-test="incidence-chart">
            <h2 className="mb-3 text-base font-medium">{heading}</h2>
            <div className="flex flex-col gap-3">
                {points.map((point) => {
                    const amount = Number(point.value);
                    const width = Number.isFinite(amount) ? Math.min(100, (amount / max) * 100) : 0;

                    return (
                        <div key={`${point.province}-${point.year}`} className="min-w-0">
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span className="min-w-0">
                                    {point.province} {point.year}
                                </span>
                                <span className="shrink-0 tabular-nums">{point.value}</span>
                            </div>
                            <div className="mt-1 h-2 rounded bg-muted">
                                <div className="h-2 rounded bg-primary" style={{ width: `${width}%` }} />
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function AddTarget({
    storeUrl,
    yearOptions,
    provinces,
    labels,
}: {
    storeUrl: string;
    yearOptions: number[];
    provinces: CommandOption[];
    labels: Props['incidenceLabels'];
}) {
    return (
        <section className="min-w-0">
            <h2 className="mb-3 text-base font-medium">{labels.add}</h2>
            <Form action={storeUrl} method="post" className="grid grid-cols-1 gap-3 sm:grid-cols-2" disableWhileProcessing>
                {({ errors, processing }) => (
                    <>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.province}</span>
                            <select
                                name="province_id"
                                required
                                defaultValue={provinces[0]?.value ?? ''}
                                aria-label={labels.province}
                                className="border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3"
                            >
                                {provinces.map((province) => (
                                    <option key={province.value} value={province.value}>
                                        {province.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.province_id} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.year}</span>
                            <select
                                name="year"
                                required
                                defaultValue={String(yearOptions[0] ?? '')}
                                aria-label={labels.year}
                                className="border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3"
                            >
                                {yearOptions.map((year) => (
                                    <option key={year} value={year}>
                                        {year}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.year} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm sm:col-span-2">
                            <span>{labels.value}</span>
                            <input
                                name="value"
                                type="number"
                                min="0"
                                step="any"
                                required
                                aria-label={labels.value}
                                className="border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3"
                            />
                            <InputError message={errors.value} />
                        </label>
                        <div>
                            <Button type="submit" disabled={processing} data-test="incidence-save">
                                {labels.save}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function EditTarget({
    storeUrl,
    target,
    saveLabel,
    valueLabel,
}: {
    storeUrl: string;
    target: TargetRow;
    saveLabel: string;
    valueLabel: string;
}) {
    return (
        <Form action={storeUrl} method="post" className="flex min-w-0 flex-wrap items-center gap-2">
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="province_id" value={target.provinceId} />
                    <input type="hidden" name="year" value={target.year} />
                    <input
                        name="value"
                        type="number"
                        min="0"
                        step="any"
                        required
                        defaultValue={target.value}
                        aria-label={valueLabel}
                        className="border-input h-9 w-24 min-w-0 rounded-md border bg-transparent px-2"
                    />
                    <Button type="submit" size="sm" disabled={processing}>
                        {saveLabel}
                    </Button>
                    <InputError message={errors.value} />
                </>
            )}
        </Form>
    );
}

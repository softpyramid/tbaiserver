import { Form } from '@inertiajs/react';
import { useState } from 'react';
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

type EstimateRow = {
    id: number;
    areaType: 'country' | 'province' | 'district';
    areaId: number | null;
    area: string;
    year: number;
    rate: string;
    lower: string | null;
    upper: string | null;
    cases: number | null;
    source: string;
    method: string;
    referenceUrl: string | null;
};

type SeriesPoint = {
    year: number;
    province: string;
    value: string;
};

type EstimateAreaOption = {
    type: 'country' | 'province';
    id: number | null;
    label: string;
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
        unit: string;
        save: string;
        add: string;
        note: string;
        estimateList: string;
        estimateAdd: string;
        estimateAreaType: string;
        estimateArea: string;
        estimateYear: string;
        estimateYearHint: string;
        estimateRate: string;
        estimateLower: string;
        estimateUpper: string;
        estimateCases: string;
        estimateSource: string;
        estimateMethod: string;
        estimateReference: string;
    };
    canEdit: boolean;
    storeUrl: string | null;
    estimateStoreUrl: string | null;
    yearOptions: number[];
    estimateYearOptions: number[];
    estimateAreas: EstimateAreaOption[];
    targetProvinces: CommandOption[];
    targets: TargetRow[];
    estimates: EstimateRow[];
    series: SeriesPoint[];
    empty: string | null;
};

export default function CommandIncidence({
    title,
    scope,
    filters,
    options,
    show,
    labels,
    links,
    incidenceLabels,
    canEdit,
    storeUrl,
    estimateStoreUrl,
    yearOptions,
    estimateYearOptions,
    estimateAreas,
    targetProvinces,
    targets,
    estimates,
    series,
    empty,
}: Props) {
    return (
        <CommandShell
            title={title}
            scope={scope}
            filters={filters}
            options={options}
            show={show}
            labels={labels}
            links={links}
        >
            <p className="text-sm text-muted-foreground">
                {incidenceLabels.note}
            </p>

            {empty ? (
                <p
                    className="text-sm text-muted-foreground"
                    data-test="incidence-empty"
                >
                    {empty}
                </p>
            ) : null}

            {series.length > 0 ? (
                <YearChart heading={incidenceLabels.chart} points={series} />
            ) : null}

            {targets.length > 0 ? (
                <section className="min-w-0" data-test="incidence-table">
                    <h2 className="mb-3 text-base font-medium">
                        {incidenceLabels.table}
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="px-2 py-2 font-medium">
                                        {incidenceLabels.province}
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        {incidenceLabels.year}
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        {incidenceLabels.value}
                                    </th>
                                    {canEdit ? (
                                        <th className="px-2 py-2 font-medium">
                                            {incidenceLabels.save}
                                        </th>
                                    ) : null}
                                </tr>
                            </thead>
                            <tbody>
                                {targets.map((target) => (
                                    <tr
                                        key={target.id}
                                        className="border-b"
                                        data-test={`incidence-row-${target.id}`}
                                    >
                                        <td className="px-2 py-2">
                                            {target.province}
                                        </td>
                                        <td className="px-2 py-2 tabular-nums">
                                            {target.year}
                                        </td>
                                        <td className="px-2 py-2 tabular-nums">
                                            {target.value}
                                        </td>
                                        {canEdit && storeUrl ? (
                                            <td className="px-2 py-2">
                                                <EditTarget
                                                    storeUrl={storeUrl}
                                                    target={target}
                                                    saveLabel={
                                                        incidenceLabels.save
                                                    }
                                                    valueLabel={
                                                        incidenceLabels.value
                                                    }
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

            {canEdit && estimateStoreUrl && estimates.length > 0 ? (
                <EstimateList
                    storeUrl={estimateStoreUrl}
                    rows={estimates}
                    yearOptions={estimateYearOptions}
                    labels={incidenceLabels}
                />
            ) : null}

            {canEdit && estimateStoreUrl && estimateAreas.length > 0 ? (
                <AddEstimate
                    storeUrl={estimateStoreUrl}
                    yearOptions={estimateYearOptions}
                    areas={estimateAreas}
                    labels={incidenceLabels}
                />
            ) : null}
        </CommandShell>
    );
}

function YearChart({
    heading,
    points,
}: {
    heading: string;
    points: SeriesPoint[];
}) {
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
                    const width = Number.isFinite(amount)
                        ? Math.min(100, (amount / max) * 100)
                        : 0;

                    return (
                        <div
                            key={`${point.province}-${point.year}`}
                            className="min-w-0"
                        >
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span className="min-w-0">
                                    {point.province} {point.year}
                                </span>
                                <span className="shrink-0 tabular-nums">
                                    {point.value}
                                </span>
                            </div>
                            <div className="mt-1 h-2 rounded bg-muted">
                                <div
                                    className="h-2 rounded bg-primary"
                                    style={{ width: `${width}%` }}
                                />
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
            <Form
                action={storeUrl}
                method="post"
                className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                disableWhileProcessing
            >
                {({ errors, processing }) => (
                    <>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.province}</span>
                            <select
                                name="province_id"
                                required
                                defaultValue={provinces[0]?.value ?? ''}
                                aria-label={labels.province}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            >
                                {provinces.map((province) => (
                                    <option
                                        key={province.value}
                                        value={province.value}
                                    >
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
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
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
                            <span>
                                {labels.value} ({labels.unit})
                            </span>
                            <input
                                name="value"
                                type="number"
                                min="0"
                                step="any"
                                required
                                aria-label={labels.value}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.value} />
                        </label>
                        <div>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="incidence-save"
                            >
                                {labels.save}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function EstimateList({
    storeUrl,
    rows,
    yearOptions,
    labels,
}: {
    storeUrl: string;
    rows: EstimateRow[];
    yearOptions: number[];
    labels: Props['incidenceLabels'];
}) {
    return (
        <section className="min-w-0" data-test="estimate-list">
            <h2 className="mb-1 text-base font-medium">
                {labels.estimateList}
            </h2>
            <p className="mb-3 text-xs text-muted-foreground">
                {labels.estimateYearHint}
            </p>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="px-2 py-2 font-medium">
                                {labels.estimateArea}
                            </th>
                            <th className="px-2 py-2 font-medium">
                                {labels.estimateRate}
                            </th>
                            <th className="px-2 py-2 font-medium">
                                {labels.estimateYear}
                            </th>
                            <th className="px-2 py-2 font-medium">
                                {labels.estimateSource}
                            </th>
                            <th className="px-2 py-2 font-medium">
                                {labels.estimateMethod}
                            </th>
                            <th className="px-2 py-2 font-medium">
                                {labels.save}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                className="border-b"
                                data-test={`estimate-row-${row.id}`}
                            >
                                <td className="px-2 py-2">{row.area}</td>
                                <td className="px-2 py-2 tabular-nums">
                                    {row.rate}
                                </td>
                                <td className="px-2 py-2" colSpan={4}>
                                    <Form
                                        action={storeUrl}
                                        method="post"
                                        className="flex min-w-0 flex-wrap items-start gap-2"
                                        disableWhileProcessing
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value={row.id}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="area_type"
                                                    value={row.areaType}
                                                />
                                                {row.areaId !== null ? (
                                                    <input
                                                        type="hidden"
                                                        name="area_id"
                                                        value={row.areaId}
                                                    />
                                                ) : null}
                                                <input
                                                    type="hidden"
                                                    name="rate_per_100k"
                                                    value={row.rate}
                                                />
                                                {row.lower !== null ? (
                                                    <input
                                                        type="hidden"
                                                        name="lower_bound"
                                                        value={row.lower}
                                                    />
                                                ) : null}
                                                {row.upper !== null ? (
                                                    <input
                                                        type="hidden"
                                                        name="upper_bound"
                                                        value={row.upper}
                                                    />
                                                ) : null}
                                                {row.cases !== null ? (
                                                    <input
                                                        type="hidden"
                                                        name="estimated_cases"
                                                        value={row.cases}
                                                    />
                                                ) : null}
                                                {row.referenceUrl ? (
                                                    <input
                                                        type="hidden"
                                                        name="reference_url"
                                                        value={row.referenceUrl}
                                                    />
                                                ) : null}
                                                <select
                                                    name="year"
                                                    required
                                                    defaultValue={String(
                                                        row.year,
                                                    )}
                                                    aria-label={
                                                        labels.estimateYear
                                                    }
                                                    className="h-9 w-24 min-w-0 rounded-md border border-input bg-transparent px-2"
                                                >
                                                    {(yearOptions.includes(
                                                        row.year,
                                                    )
                                                        ? yearOptions
                                                        : [
                                                              row.year,
                                                              ...yearOptions,
                                                          ]
                                                    ).map((year) => (
                                                        <option
                                                            key={year}
                                                            value={year}
                                                        >
                                                            {year}
                                                        </option>
                                                    ))}
                                                </select>
                                                <input
                                                    name="source"
                                                    type="text"
                                                    required
                                                    defaultValue={row.source}
                                                    aria-label={
                                                        labels.estimateSource
                                                    }
                                                    className="h-9 min-w-40 flex-1 rounded-md border border-input bg-transparent px-2"
                                                />
                                                <input
                                                    name="method"
                                                    type="text"
                                                    required
                                                    defaultValue={row.method}
                                                    aria-label={
                                                        labels.estimateMethod
                                                    }
                                                    className="h-9 min-w-40 flex-1 rounded-md border border-input bg-transparent px-2"
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    {labels.save}
                                                </Button>
                                                <InputError
                                                    message={errors.year}
                                                />
                                                <InputError
                                                    message={errors.source}
                                                />
                                                <InputError
                                                    message={errors.method}
                                                />
                                            </>
                                        )}
                                    </Form>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function AddEstimate({
    storeUrl,
    yearOptions,
    areas,
    labels,
}: {
    storeUrl: string;
    yearOptions: number[];
    areas: EstimateAreaOption[];
    labels: Props['incidenceLabels'];
}) {
    const [areaType, setAreaType] = useState('country');
    const provinceAreas = areas.filter((area) => area.type === 'province');
    const typeOptions = [
        ...(areas.some((area) => area.type === 'country')
            ? [
                  {
                      value: 'country',
                      label:
                          areas.find((area) => area.type === 'country')
                              ?.label ?? '',
                  },
              ]
            : []),
        ...(areas.some((area) => area.type === 'province')
            ? [{ value: 'province', label: labels.province }]
            : []),
    ];

    return (
        <section className="min-w-0">
            <h2 className="mb-3 text-base font-medium">{labels.estimateAdd}</h2>
            <Form
                action={storeUrl}
                method="post"
                className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                disableWhileProcessing
            >
                {({ errors, processing }) => (
                    <>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateAreaType}</span>
                            <select
                                name="area_type"
                                required
                                value={areaType}
                                onChange={(event) =>
                                    setAreaType(event.target.value)
                                }
                                aria-label={labels.estimateAreaType}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            >
                                {typeOptions.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.area_type} />
                        </label>
                        {areaType === 'country' ? (
                            <label className="flex min-w-0 flex-col gap-1 text-sm">
                                <span>{labels.estimateArea}</span>
                                <input
                                    type="text"
                                    disabled
                                    value={
                                        typeOptions.find(
                                            (type) => type.value === 'country',
                                        )?.label ?? ''
                                    }
                                    aria-label={labels.estimateArea}
                                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 opacity-60"
                                />
                            </label>
                        ) : provinceAreas.length > 0 ? (
                            <label className="flex min-w-0 flex-col gap-1 text-sm">
                                <span>{labels.estimateArea}</span>
                                <select
                                    name="area_id"
                                    required
                                    defaultValue={provinceAreas[0]?.id ?? ''}
                                    aria-label={labels.estimateArea}
                                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                                >
                                    {provinceAreas.map((area) => (
                                        <option
                                            key={area.id}
                                            value={area.id ?? ''}
                                        >
                                            {area.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.area_id} />
                            </label>
                        ) : null}
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateYear}</span>
                            <select
                                name="year"
                                required
                                defaultValue={String(yearOptions[0] ?? '')}
                                aria-label={labels.estimateYear}
                                aria-describedby="estimate-year-hint"
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            >
                                {yearOptions.map((year) => (
                                    <option key={year} value={year}>
                                        {year}
                                    </option>
                                ))}
                            </select>
                            <p
                                id="estimate-year-hint"
                                className="text-xs text-muted-foreground"
                            >
                                {labels.estimateYearHint}
                            </p>
                            <InputError message={errors.year} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateRate}</span>
                            <input
                                name="rate_per_100k"
                                type="number"
                                min="0"
                                step="any"
                                required
                                aria-label={labels.estimateRate}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.rate_per_100k} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateLower}</span>
                            <input
                                name="lower_bound"
                                type="number"
                                min="0"
                                step="any"
                                aria-label={labels.estimateLower}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.lower_bound} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateUpper}</span>
                            <input
                                name="upper_bound"
                                type="number"
                                min="0"
                                step="any"
                                aria-label={labels.estimateUpper}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.upper_bound} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateCases}</span>
                            <input
                                name="estimated_cases"
                                type="number"
                                min="0"
                                step="any"
                                aria-label={labels.estimateCases}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.estimated_cases} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateSource}</span>
                            <input
                                name="source"
                                type="text"
                                required
                                aria-label={labels.estimateSource}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.source} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm">
                            <span>{labels.estimateMethod}</span>
                            <input
                                name="method"
                                type="text"
                                required
                                aria-label={labels.estimateMethod}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.method} />
                        </label>
                        <label className="flex min-w-0 flex-col gap-1 text-sm sm:col-span-2">
                            <span>{labels.estimateReference}</span>
                            <input
                                name="reference_url"
                                type="url"
                                aria-label={labels.estimateReference}
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                            />
                            <InputError message={errors.reference_url} />
                        </label>
                        <div>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="estimate-save"
                            >
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
        <Form
            action={storeUrl}
            method="post"
            className="flex min-w-0 flex-wrap items-center gap-2"
            disableWhileProcessing
        >
            {({ errors, processing }) => (
                <>
                    <input
                        type="hidden"
                        name="province_id"
                        value={target.provinceId}
                    />
                    <input type="hidden" name="year" value={target.year} />
                    <input
                        name="value"
                        type="number"
                        min="0"
                        step="any"
                        required
                        defaultValue={target.value}
                        aria-label={valueLabel}
                        className="h-9 w-24 min-w-0 rounded-md border border-input bg-transparent px-2"
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

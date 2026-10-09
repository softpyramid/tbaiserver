import { router, useForm } from '@inertiajs/react';
import PageShell, { type ShellTab } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type AreaOption = {
    value: string;
    label: string;
};

type Locked = {
    area_type: string;
    area_id: string;
    year: string;
};

type Row = {
    area: string;
    year: number;
    population: number | null;
    status: 'confirmed' | 'placeholder';
    source: string;
    source_year: number | null;
    reference_url: string | null;
    method_note: string | null;
};

type Props = {
    title: string;
    heading: string;
    description: string;
    tabs: ShellTab[];
    mode: 'create' | 'edit';
    locked: Locked | null;
    action: string;
    method: 'post' | 'patch';
    backUrl: string;
    importUrl: string;
    areas: AreaOption[];
    row: Row | null;
};

export default function PopulationForm({
    title,
    heading,
    description,
    tabs,
    mode,
    locked,
    action,
    method,
    backUrl,
    importUrl,
    areas,
    row,
}: Props) {
    const form = useForm({
        area: locked ? `${locked.area_type}:${locked.area_id}` : '',
        year: row ? String(row.year) : String(new Date().getFullYear()),
        population:
            row?.population === null ? '' : String(row?.population ?? ''),
        source: row?.source ?? '',
        source_year:
            row?.source_year === null ? '' : String(row?.source_year ?? ''),
        reference_url: row?.reference_url ?? '',
        method_note: row?.method_note ?? '',
    });
    const busy = form.processing;

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const data: Record<string, string | number> = {
            population:
                form.data.population === '' ? '' : Number(form.data.population),
            source: form.data.source,
            source_year:
                form.data.source_year === ''
                    ? ''
                    : Number(form.data.source_year),
            reference_url: form.data.reference_url,
            method_note: form.data.method_note,
        };

        if (mode === 'create') {
            const [areaType, areaId] = form.data.area.split(':');
            data.area_type = areaType;
            data.area_id = areaId;
            data.year = Number(form.data.year);
        }

        router[method](action, data, {
            preserveScroll: true,
            onError: (errors) => {
                form.setError(errors);
            },
        });
    }

    return (
        <PageShell
            title={heading}
            headTitle={title}
            description={description}
            tabs={tabs}
        >
            <div className="max-w-xl space-y-4">
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <a href={importUrl}>Import a CSV instead</a>
                    </Button>
                </div>
                <form
                    className="space-y-4"
                    onSubmit={submit}
                    data-test="population-form"
                >
                    {mode === 'create' ? (
                        <label className="flex flex-col gap-1 text-sm">
                            <span>Area</span>
                            <select
                                name="area"
                                value={form.data.area}
                                onChange={(event) =>
                                    form.setData('area', event.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base md:text-sm"
                            >
                                <option value="">Choose an area</option>
                                {areas.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.area ? (
                                <span className="text-sm text-destructive">
                                    {form.errors.area}
                                </span>
                            ) : null}
                        </label>
                    ) : (
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Area
                                </p>
                                <p className="text-sm font-medium">
                                    {row?.area}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Year
                                </p>
                                <p className="text-sm font-medium">
                                    {row?.year}
                                </p>
                            </div>
                        </div>
                    )}

                    {mode === 'create' ? (
                        <label className="flex flex-col gap-1 text-sm">
                            <span>Reporting year</span>
                            <Input
                                name="year"
                                type="number"
                                min={1900}
                                max={2100}
                                value={form.data.year}
                                onChange={(event) =>
                                    form.setData('year', event.target.value)
                                }
                            />
                            {form.errors.year ? (
                                <span className="text-sm text-destructive">
                                    {form.errors.year}
                                </span>
                            ) : null}
                        </label>
                    ) : null}

                    <label className="flex flex-col gap-1 text-sm">
                        <span>Population</span>
                        <Input
                            name="population"
                            type="number"
                            min={1}
                            placeholder="e.g. 55696147"
                            value={form.data.population}
                            onChange={(event) =>
                                form.setData('population', event.target.value)
                            }
                        />
                        {mode === 'edit' && row?.status === 'placeholder' ? (
                            <span className="text-xs text-muted-foreground">
                                Enter the PBS Census 2023 figure for this area.
                            </span>
                        ) : null}
                        {form.errors.population ? (
                            <span className="text-sm text-destructive">
                                {form.errors.population}
                            </span>
                        ) : null}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span>Source</span>
                        <Input
                            name="source"
                            placeholder="PBS Census 2023"
                            value={form.data.source}
                            onChange={(event) =>
                                form.setData('source', event.target.value)
                            }
                        />
                        {form.errors.source ? (
                            <span className="text-sm text-destructive">
                                {form.errors.source}
                            </span>
                        ) : null}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span>Source year</span>
                        <Input
                            name="source_year"
                            type="number"
                            min={1900}
                            max={2100}
                            placeholder="2023"
                            value={form.data.source_year}
                            onChange={(event) =>
                                form.setData('source_year', event.target.value)
                            }
                        />
                        {form.errors.source_year ? (
                            <span className="text-sm text-destructive">
                                {form.errors.source_year}
                            </span>
                        ) : null}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span>Reference URL</span>
                        <Input
                            name="reference_url"
                            type="url"
                            placeholder="https://www.pbs.gov.pk/…"
                            value={form.data.reference_url}
                            onChange={(event) =>
                                form.setData(
                                    'reference_url',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.reference_url ? (
                            <span className="text-sm text-destructive">
                                {form.errors.reference_url}
                            </span>
                        ) : null}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span>Method note</span>
                        <textarea
                            name="method_note"
                            value={form.data.method_note}
                            onChange={(event) =>
                                form.setData('method_note', event.target.value)
                            }
                            className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                        />
                        {form.errors.method_note ? (
                            <span className="text-sm text-destructive">
                                {form.errors.method_note}
                            </span>
                        ) : null}
                    </label>

                    <div className="flex items-center gap-2">
                        <Button type="submit" disabled={busy}>
                            {mode === 'create' ? 'Add' : 'Save'}
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={backUrl}>Back</a>
                        </Button>
                    </div>
                </form>
            </div>
        </PageShell>
    );
}

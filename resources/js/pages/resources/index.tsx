import { Head, Link, router, usePage } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type Option = {
    value: string;
    label: string;
};

type FilterField = {
    key: string;
    label: string;
    type: 'search' | 'select' | 'date';
    value: string;
    options?: Option[];
};

type Column = {
    key: string;
    label: string;
};

type Control =
    | {
          kind: 'link';
          url: string;
          label: string;
      }
    | {
          kind: 'button';
          url: string;
          method: 'post' | 'patch';
          label: string;
          person?: string;
          confirm?: boolean;
          fields: { name: string; value: string }[];
      }
    | {
          kind: 'select';
          url: string;
          method: 'post' | 'patch';
          name: string;
          value: string;
          label: string;
          person?: string;
          options: Option[];
      };

type Row = {
    id: string;
    href: string | null;
    cells: Record<string, string>;
    controls: Control[];
};

type Labels = {
    export: string;
    apply: string;
    clear: string;
    search: string;
    previous: string;
    next: string;
    empty: string;
    sort: string;
};

type Props = {
    title: string;
    columns: Column[];
    rows: Row[];
    filters: FilterField[];
    sort: string;
    direction: 'asc' | 'desc';
    showing: string;
    previousUrl: string | null;
    nextUrl: string | null;
    createUrl: string | null;
    createLabel: string | null;
    exportUrl: string;
    labels: Labels;
    status: string | null;
};

function pathOf(url: string): string {
    return url.split('?')[0] ?? url;
}

function actionName(label: string, person?: string): string {
    return person ? `${label} ${person}` : label;
}

function fieldData(fields: { name: string; value: string }[]): Record<string, string> {
    return Object.fromEntries(fields.map((field) => [field.name, field.value]));
}

export default function ResourceIndex({
    title,
    columns,
    rows,
    filters,
    sort,
    direction,
    showing,
    previousUrl,
    nextUrl,
    createUrl,
    createLabel,
    exportUrl,
    labels,
    status,
}: Props) {
    const page = usePage();
    const path = pathOf(page.url);
    const [pending, setPending] = useState<Extract<Control, { kind: 'button' }> | null>(null);
    const [confirming, setConfirming] = useState(false);
    const hasControls = rows.some((row) => row.controls.length > 0);

    function currentQuery(extra: Record<string, string> = {}): Record<string, string> {
        const query: Record<string, string> = {};

        for (const filter of filters) {
            if (filter.value !== '') {
                query[filter.key] = filter.value;
            }
        }

        return { ...query, ...extra };
    }

    function onFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const query: Record<string, string> = {};

        data.forEach((value, key) => {
            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        });

        router.get(path, query, { preserveState: true, preserveScroll: true });
    }

    function onSort(key: string) {
        const nextDirection = sort === key && direction === 'asc' ? 'desc' : 'asc';

        router.get(
            path,
            currentQuery({ sort: key, direction: nextDirection }),
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-6 px-4 py-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-xl font-semibold">{title}</h1>
                    <div className="flex flex-wrap gap-2">
                        {createUrl && createLabel ? (
                            <Button asChild>
                                <Link href={createUrl}>{createLabel}</Link>
                            </Button>
                        ) : null}
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>{labels.export}</a>
                        </Button>
                    </div>
                </div>

                {status ? (
                    <p className="text-sm text-muted-foreground">{status}</p>
                ) : null}

                {filters.length > 0 ? (
                    <form
                        className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                        onSubmit={onFilter}
                    >
                        {filters.map((filter) => (
                            <label key={filter.key} className="flex flex-col gap-1 text-sm">
                                <span>{filter.label}</span>
                                {filter.type === 'select' ? (
                                    <select
                                        name={filter.key}
                                        defaultValue={filter.value}
                                        className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-base md:text-sm"
                                    >
                                        {filter.options?.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <Input
                                        name={filter.key}
                                        type={filter.type === 'date' ? 'date' : 'search'}
                                        defaultValue={filter.value}
                                        aria-label={filter.label}
                                    />
                                )}
                            </label>
                        ))}
                        <div className="flex items-end gap-2">
                            <Button type="submit">{labels.apply}</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.get(path, {}, { preserveScroll: true })
                                }
                            >
                                {labels.clear}
                            </Button>
                        </div>
                    </form>
                ) : null}

                {showing ? (
                    <p className="text-sm text-muted-foreground">{showing}</p>
                ) : null}

                {rows.length > 0 ? (
                    <ul className="space-y-3 md:hidden">
                        {rows.map((row) => (
                            <li key={row.id} className="relative rounded-lg border p-3">
                                {row.href && row.controls.length === 0 ? (
                                    <RowLink row={row} columns={columns} />
                                ) : null}
                                <RowBody
                                    row={row}
                                    columns={columns}
                                    linked={Boolean(row.href) && row.controls.length === 0}
                                    onConfirm={setPending}
                                />
                            </li>
                        ))}
                    </ul>
                ) : null}

                <div className={rows.length === 0 ? 'overflow-x-auto' : 'hidden overflow-x-auto md:block'}>
                    <table className="w-full border-collapse text-sm" data-test="resource-table">
                        <thead>
                            <tr className="border-b text-left">
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        className="px-2 py-2 font-medium"
                                        data-test="resource-column"
                                    >
                                        <button
                                            type="button"
                                            className="underline-offset-2 hover:underline"
                                            onClick={() => onSort(column.key)}
                                        >
                                            {column.label}
                                            {sort === column.key
                                                ? direction === 'asc'
                                                    ? ' ↑'
                                                    : ' ↓'
                                                : ''}
                                            <span className="sr-only">
                                                {labels.sort}
                                            </span>
                                        </button>
                                    </th>
                                ))}
                                {hasControls ? (
                                    <th className="px-2 py-2 font-medium">Actions</th>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={columns.length} className="px-2 py-4">
                                        <p
                                            className="text-sm text-muted-foreground"
                                            data-test="resource-empty"
                                        >
                                            {labels.empty}
                                        </p>
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => {
                                    const wholeRow =
                                        Boolean(row.href) && row.controls.length === 0;

                                    return (
                                    <tr key={row.id} className="relative border-b align-top">
                                        {columns.map((column) => (
                                            <td key={column.key} className="px-2 py-2">
                                                {column.key === columns[0]?.key && wholeRow ? (
                                                    <RowLink row={row} columns={columns} />
                                                ) : null}
                                                {column.key === columns[0]?.key && !wholeRow ? (
                                                    <CellLink row={row} value={row.cells[column.key] ?? ''} />
                                                ) : (
                                                    row.cells[column.key]
                                                )}
                                            </td>
                                        ))}
                                        {hasControls ? (
                                            <td className="relative z-20 px-2 py-2">
                                                <RowMenu
                                                    controls={row.controls}
                                                    onConfirm={setPending}
                                                />
                                            </td>
                                        ) : null}
                                    </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex gap-2">
                    {previousUrl ? (
                        <Button variant="outline" asChild>
                            <Link href={previousUrl}>{labels.previous}</Link>
                        </Button>
                    ) : null}
                    {nextUrl ? (
                        <Button variant="outline" asChild>
                            <Link href={nextUrl}>{labels.next}</Link>
                        </Button>
                    ) : null}
                </div>
            </div>
            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open && !confirming) {
                        setPending(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {pending
                                ? `${pending.label} ${pending.person ?? ''}`.trim()
                                : ''}
                        </DialogTitle>
                        <DialogDescription>
                            {pending
                                ? `Confirm ${pending.label.toLowerCase()}${pending.person ? ` for ${pending.person}` : ''}.`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={confirming}
                            onClick={() => setPending(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={confirming || pending === null}
                            onClick={() => {
                                if (pending === null || confirming) {
                                    return;
                                }

                                setConfirming(true);
                                router.visit(pending.url, {
                                    method: pending.method,
                                    data: fieldData(pending.fields),
                                    preserveScroll: true,
                                    onFinish: () => {
                                        setConfirming(false);
                                        setPending(null);
                                    },
                                });
                            }}
                        >
                            {pending?.label ?? 'Confirm'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function RowBody({
    row,
    columns,
    linked,
    onConfirm,
}: {
    row: Row;
    columns: Column[];
    linked: boolean;
    onConfirm: (control: Extract<Control, { kind: 'button' }>) => void;
}) {
    return (
        <div className="relative flex flex-col gap-2">
            {columns.map((column) => (
                <div key={column.key} className="relative">
                    <div className="text-sm text-muted-foreground">{column.label}</div>
                    <div>
                        {column.key === columns[0]?.key && !linked ? (
                            <CellLink row={row} value={row.cells[column.key] ?? ''} />
                        ) : (
                            row.cells[column.key]
                        )}
                    </div>
                </div>
            ))}
            <Controls controls={row.controls} onConfirm={onConfirm} />
        </div>
    );
}

function RowLink({ row, columns }: { row: Row; columns: Column[] }) {
    if (!row.href) {
        return null;
    }

    const label = row.cells[columns[0]?.key ?? ''] ?? 'Open';

    return (
        <Link
            href={row.href}
            className="absolute inset-0 z-10"
            aria-label={`Open ${label}`}
            data-test="row-link"
        >
            <span className="sr-only">{label}</span>
        </Link>
    );
}

function RowMenu({
    controls,
    onConfirm,
}: {
    controls: Control[];
    onConfirm: (control: Extract<Control, { kind: 'button' }>) => void;
}) {
    if (controls.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon" aria-label="Row actions">
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56 p-2">
                <Controls controls={controls} onConfirm={onConfirm} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function CellLink({ row, value }: { row: Row; value: string }) {
    if (!row.href) {
        return <span className="font-medium">{value}</span>;
    }

    return (
        <Link href={row.href} className="font-medium underline-offset-2 hover:underline">
            {value}
        </Link>
    );
}

function Controls({
    controls,
    onConfirm,
}: {
    controls: Control[];
    onConfirm: (control: Extract<Control, { kind: 'button' }>) => void;
}) {
    if (controls.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 pt-2">
            {controls.map((control) => {
                switch (control.kind) {
                    case 'link':
                        return (
                            <Button key={control.url + control.label} variant="outline" asChild>
                                <Link href={control.url}>{control.label}</Link>
                            </Button>
                        );
                    case 'select':
                        return (
                            <label key={control.url + control.label} className="flex flex-col gap-1 text-sm">
                                <span>{control.label}</span>
                                <select
                                    className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-base md:text-sm"
                                    defaultValue={control.value}
                                    aria-label={actionName(control.label, control.person)}
                                    onChange={(event) => {
                                        router.visit(control.url, {
                                            method: control.method,
                                            data: { [control.name]: event.target.value },
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    {control.options.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        );
                    case 'button':
                        return (
                            <Button
                                key={control.url + control.label}
                                type="button"
                                variant="outline"
                                aria-label={actionName(control.label, control.person)}
                                onClick={() => {
                                    if (control.confirm) {
                                        onConfirm(control);
                                        return;
                                    }

                                    router.visit(control.url, {
                                        method: control.method,
                                        data: fieldData(control.fields),
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                {control.label}
                            </Button>
                        );
                    default: {
                        const unreachable: never = control;

                        return unreachable;
                    }
                }
            })}
        </div>
    );
}

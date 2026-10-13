import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import PageShell, {
    type ShellTab,
    type ShellTile,
} from '@/components/page-shell';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    description: string;
    tabs?: ShellTab[];
    tiles?: ShellTile[];
    action: string;
    backUrl: string;
    columns: string[];
    sample: string;
    margin?: string;
};

export default function ResourceImport({
    title,
    description,
    tabs = [],
    tiles = [],
    action,
    backUrl,
    columns,
    sample,
}: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!file) {
            setError('Choose a CSV file first.');
            return;
        }

        setBusy(true);
        setError(null);

        router.post(
            action,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onError: (errors) => {
                    setError(
                        Object.values(errors).join(' ') || 'Import failed.',
                    );
                },
            },
        );
    }

    return (
        <PageShell
            title={title}
            headTitle={title}
            description={description}
            tabs={tabs}
            tiles={tiles}
        >
            <form
                className="max-w-xl space-y-4"
                onSubmit={submit}
                data-test="import-form"
            >
                <label className="flex flex-col gap-1 text-sm">
                    <span>CSV file</span>
                    <input
                        type="file"
                        accept=".csv,text/csv"
                        data-test="import-file"
                        className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground"
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                    />
                </label>
                <p className="text-sm text-muted-foreground">
                    Expected columns: {columns.join(', ')}
                </p>
                <pre className="overflow-x-auto rounded-lg border bg-muted/40 p-3 text-xs">
                    {sample}
                </pre>
                {error ? (
                    <p
                        className="text-sm text-destructive"
                        data-test="import-error"
                    >
                        {error}
                    </p>
                ) : null}
                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={busy || file === null}>
                        Import
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={backUrl}>Back</a>
                    </Button>
                </div>
            </form>
        </PageShell>
    );
}

import { Head, Link } from '@inertiajs/react';

type Props = {
    title: string;
    scope: string;
    intro: string;
    facilitiesLine: string;
    returnsLine: string;
    facilitiesUrl: string;
    facilitiesLabel: string;
    returnsUrl: string;
    returnsLabel: string;
};

export default function CommandIndex({
    title,
    scope,
    intro,
    facilitiesLine,
    returnsLine,
    facilitiesUrl,
    facilitiesLabel,
    returnsUrl,
    returnsLabel,
}: Props) {
    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-md flex-col gap-6 px-4 py-6">
                <h1 className="text-xl font-semibold">{title}</h1>
                {scope ? <p className="text-sm text-muted-foreground">{scope}</p> : null}
                <p>{intro}</p>
                <p>{facilitiesLine}</p>
                <p>{returnsLine}</p>
                <div className="flex flex-col gap-3">
                    <Link href={facilitiesUrl} className="font-medium underline-offset-2 hover:underline">
                        {facilitiesLabel}
                    </Link>
                    <Link href={returnsUrl} className="font-medium underline-offset-2 hover:underline">
                        {returnsLabel}
                    </Link>
                </div>
            </div>
        </>
    );
}

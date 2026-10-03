import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { MarketingShell } from '@/components/marketing-shell';
import { Button } from '@/components/ui/button';

type Props = {
    status: number;
    title: string;
    reason: string;
    homeUrl: string;
    homeLabel: string;
};

export default function HttpError({
    status,
    title,
    reason,
    homeUrl,
    homeLabel,
}: Props) {
    const { auth } = usePage().props;
    const body = (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-16">
                <p className="text-sm font-medium text-muted-foreground">
                    {status}
                </p>
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p role="alert">{reason}</p>
                <div>
                    <Button asChild>
                        <Link href={homeUrl}>{homeLabel}</Link>
                    </Button>
                </div>
            </div>
        </>
    );

    if (auth.user) {
        return <AppLayout>{body}</AppLayout>;
    }

    return <MarketingShell>{body}</MarketingShell>;
}

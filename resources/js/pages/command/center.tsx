import { Head } from '@inertiajs/react';
import '../../../css/command-center.css';
import { CommandShell } from '../../command-center/shell';

export default function CommandCenterPage({
    backUrl,
    backLabel,
}: {
    backUrl: string;
    backLabel: string;
}) {
    return (
        <>
            <Head title="Command Center" />
            <CommandShell backUrl={backUrl} backLabel={backLabel} />
        </>
    );
}

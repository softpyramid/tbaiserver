import { Head } from '@inertiajs/react';
import '../../../css/command-center.css';
import { CommandShell } from '../../command-center/shell';

export default function CommandCenterPage({ backUrl }: { backUrl: string }) {
    return (
        <>
            <Head title="Command Center" />
            <CommandShell backUrl={backUrl} />
        </>
    );
}

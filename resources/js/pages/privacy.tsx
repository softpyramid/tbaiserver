import { Head } from '@inertiajs/react';
import {
    contactEmail,
    contactName,
    MarketingShell,
} from '@/components/marketing-shell';

export default function Privacy() {
    return (
        <MarketingShell>
            <Head title="Privacy" />
            <main className="mx-auto max-w-3xl px-5 py-16 sm:px-8">
                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase">
                    Draft, pending legal review
                </p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Privacy
                </h1>
                <div className="mt-6 space-y-4 text-base leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                    <p>
                        This page will describe what TB-AI collects, why it is
                        kept, who can see it, and how long it is held. It is a
                        placeholder. It is not a finished privacy notice.
                    </p>
                    <p>
                        Until legal review is complete, do not treat this page
                        as the programme's privacy policy. A request for access
                        stores your name, role, organisation, district, and
                        email so the request can be reviewed. It does not
                        create an account.
                    </p>
                    <p>
                        Questions about this draft: {contactName},{' '}
                        <a
                            className="underline decoration-[#1E88E5] underline-offset-4 hover:text-[#1A237E] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5] dark:hover:text-white"
                            href={`mailto:${contactEmail}`}
                        >
                            {contactEmail}
                        </a>
                    </p>
                </div>
            </main>
        </MarketingShell>
    );
}

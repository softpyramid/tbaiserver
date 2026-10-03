import { Head } from '@inertiajs/react';
import {
    contactEmail,
    contactName,
    developmentNotice,
    MarketingShell,
} from '@/components/marketing-shell';

export default function Terms() {
    return (
        <MarketingShell>
            <Head title="Terms" />
            <main className="mx-auto max-w-3xl px-5 py-16 sm:px-8">
                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase">
                    Draft, pending legal review
                </p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Terms
                </h1>
                <div className="mt-6 space-y-4 text-base leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                    <p>
                        This page will set the terms for using TB-AI. It is a
                        placeholder. It is not a finished agreement.
                    </p>
                    <p>
                        Access is by request. An account is created only when
                        someone is invited. Signing in is for people who
                        already have an account.
                    </p>
                    <p>{developmentNotice}</p>
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

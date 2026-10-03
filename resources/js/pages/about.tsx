import { Head } from '@inertiajs/react';
import { MarketingShell } from '@/components/marketing-shell';

export default function About() {
    return (
        <MarketingShell>
            <Head title="About" />
            <main className="mx-auto max-w-3xl px-5 py-16 sm:px-8">
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                    About TB-AI
                </h1>
                <div className="mt-6 space-y-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                    <p>
                        TB-AI is a register and decision-support tool for TB
                        programme staff. Facilities enter daily counts.
                        City, provincial, and country viewers read those counts
                        for their area.
                    </p>
                    <p>
                        It does not diagnose patients and it does not store
                        patient names, phone numbers, or national IDs.
                    </p>
                </div>
            </main>
        </MarketingShell>
    );
}

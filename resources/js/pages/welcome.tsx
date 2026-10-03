import { Head, usePage } from '@inertiajs/react';
import {
    AuthActions,
    developmentNotice,
    MarketingShell,
} from '@/components/marketing-shell';
import { dashboard } from '@/routes';

const pathway = [
    {
        step: '01',
        title: 'Suspects',
        detail: 'People who enter the diagnostic pathway.',
    },
    {
        step: '02',
        title: 'Tested',
        detail: 'Smears actually read, not only referred.',
    },
    {
        step: '03',
        title: 'Diagnosed',
        detail: 'Positive and negative, from the same return.',
    },
    {
        step: '04',
        title: 'On treatment',
        detail: 'A diagnosis that has a treatment start.',
    },
    {
        step: '05',
        title: 'Still in care',
        detail: 'Patients still on treatment or follow-up',
    },
] as const;

const rooms = [
    {
        kicker: 'In the lab',
        title: 'Scope, snap, upload.',
        body: 'A technician photographs the smear on the microscope already on the bench. TB-AI is in development as decision support for that photograph. It does not return a result on its own.',
        points: [
            'Standard microscope, mid-range phone',
            'The microscopist stays in the decision',
        ],
    },
    {
        kicker: 'At the programme',
        title: 'One register for the day.',
        body: 'Each facility files one return for one date: the same counts as the paper register. Counts roll up from that facility to its district.',
        points: [
            'Suspects, tested, positive, treatment, follow-up',
            'Median turnaround on the same row',
            'Where the pathway is slow, or a case has no treatment start',
        ],
    },
] as const;

const registerFields = [
    'Suspects identified',
    'Tested',
    'TB positive',
    'TB negative',
    'Started treatment',
    'Follow-up',
    'Median turnaround',
] as const;

const previewDistricts = [
    { name: 'Karachi West', width: '86%' },
    { name: 'Tharparkar', width: '64%' },
    { name: 'Larkana', width: '52%' },
    { name: 'Thatta', width: '41%' },
    { name: 'Badin', width: '33%' },
] as const;

export default function Welcome() {
    const { auth, currentTeam } = usePage().props;
    const signedIn = Boolean(auth.user);
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <>
            <Head title="" />

            <MarketingShell>
                <main id="top">
                    <section className="bg-[#1A237E] text-[#F8FAFC]">
                        <div className="mx-auto grid max-w-6xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:py-24">
                            <div>
                                <p className="text-xs font-medium tracking-[0.22em] text-[#F8FAFC] uppercase">
                                    In development
                                </p>
                                <h1 className="mt-5 max-w-xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.4rem]">
                                    Support for the microscopist. A register
                                    for the province.
                                </h1>
                                <p
                                    className="mt-6 max-w-lg rounded-md border border-[#1E88E5]/50 bg-[#1E88E5]/15 px-4 py-3 text-sm leading-relaxed text-[#F8FAFC]"
                                    role="note"
                                >
                                    {developmentNotice}
                                </p>
                                <p className="mt-6 max-w-lg text-base leading-relaxed text-[#E8EAF6] sm:text-lg">
                                    TB-AI is in development as decision support
                                    beside the microscopist, and as the daily
                                    register behind that work. Facilities file
                                    the counts. The programme sees testing,
                                    treatment, and delay without waiting on a
                                    spreadsheet.
                                </p>
                                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                    <AuthActions
                                        signedIn={signedIn}
                                        dashboardUrl={dashboardUrl}
                                        tone="hero"
                                    />
                                </div>
                            </div>

                            <Preview />
                        </div>
                    </section>

                    <section
                        id="pathway"
                        className="scroll-mt-20 border-b border-[#1A237E]/10 dark:border-white/10"
                    >
                        <div className="mx-auto max-w-6xl px-5 py-16 sm:px-8 lg:py-20">
                            <div className="max-w-2xl">
                                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase dark:text-[#E53935]">
                                    The pathway
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    Five counts. One day on the register.
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                                    The paper register already records this
                                    path. TB-AI keeps it as one return per
                                    facility, per date, and rolls it up to the
                                    district.
                                </p>
                            </div>
                            <ol className="mt-10 grid gap-px overflow-hidden rounded-2xl border border-[#1A237E]/10 bg-[#1A237E]/10 sm:grid-cols-2 lg:grid-cols-5 dark:border-white/10 dark:bg-white/10">
                                {pathway.map((item) => (
                                    <li
                                        key={item.step}
                                        className="bg-[#F8FAFC] p-5 dark:bg-[#121858]"
                                    >
                                        <span className="font-mono text-xs text-[#E53935] dark:text-[#E53935]">
                                            {item.step}
                                        </span>
                                        <h3 className="mt-3 text-lg font-semibold">
                                            {item.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                                            {item.detail}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section
                        id="platform"
                        className="scroll-mt-20 border-b border-[#1A237E]/10 dark:border-white/10"
                    >
                        <div className="mx-auto grid max-w-6xl gap-12 px-5 py-16 sm:px-8 lg:grid-cols-2 lg:py-20">
                            {rooms.map((room) => (
                                <article key={room.kicker}>
                                    <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase dark:text-[#E53935]">
                                        {room.kicker}
                                    </p>
                                    <h2 className="mt-3 text-3xl font-semibold tracking-tight">
                                        {room.title}
                                    </h2>
                                    <p className="mt-4 text-base leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                                        {room.body}
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        {room.points.map((point) => (
                                            <li
                                                key={point}
                                                className="flex gap-3 text-sm leading-relaxed"
                                            >
                                                <span
                                                    aria-hidden
                                                    className="mt-2 size-1.5 shrink-0 rounded-full bg-[#E53935] dark:bg-[#E53935]"
                                                />
                                                <span>{point}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section
                        id="register"
                        className="scroll-mt-20 border-b border-[#1A237E]/10 dark:border-white/10"
                    >
                        <div className="mx-auto grid max-w-6xl items-start gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:py-20">
                            <div>
                                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase dark:text-[#E53935]">
                                    The daily return
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    What a facility files before the day is
                                    over.
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                                    One row is one facility and one calendar
                                    date. Positivity, coverage, and treatment
                                    gap are read from that row. They are not
                                    typed in as a second set of numbers.
                                </p>
                            </div>
                            <ul className="divide-y divide-[#1A237E]/10 border-y border-[#1A237E]/10 dark:divide-white/10 dark:border-white/10">
                                {registerFields.map((field, index) => (
                                    <li
                                        key={field}
                                        className="flex items-baseline justify-between gap-6 py-4"
                                    >
                                        <span className="font-mono text-xs text-[#E53935] dark:text-[#E53935]">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <span className="flex-1 text-base font-medium">
                                            {field}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>

                    <section className="bg-[#1A237E] text-[#F8FAFC]">
                        <div className="mx-auto flex max-w-6xl flex-col items-start justify-between gap-8 px-5 py-16 sm:px-8 lg:flex-row lg:items-center lg:py-20">
                            <div className="max-w-xl">
                                <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    Open the register.
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#E8EAF6]">
                                    Sign in to file today's return, or to read
                                    the facilities you are responsible for.
                                </p>
                            </div>
                            <AuthActions
                                signedIn={signedIn}
                                dashboardUrl={dashboardUrl}
                                tone="hero"
                            />
                        </div>
                    </section>
                </main>
            </MarketingShell>
        </>
    );
}

function Preview() {
    return (
        <div className="grid gap-4">
            <div className="rounded-2xl border border-white/10 bg-[#121858] p-5 shadow-[0_30px_80px_-40px_rgba(0,0,0,0.8)]">
                <div className="flex items-center justify-between gap-4">
                    <p className="text-xs font-medium tracking-[0.16em] text-[#F8FAFC] uppercase">
                        Provincial view
                    </p>
                    <p className="text-xs text-[#E8EAF6]/80">Schematic</p>
                </div>
                <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {['Suspects', 'Tested', 'Positive', 'Turnaround'].map(
                        (label) => (
                            <div
                                key={label}
                                className="rounded-lg bg-white/5 px-3 py-3"
                            >
                                <p className="text-[11px] tracking-wide text-[#E8EAF6]/80 uppercase">
                                    {label}
                                </p>
                                <p className="mt-2 text-sm text-[#F8FAFC]">
                                    No data yet
                                </p>
                            </div>
                        ),
                    )}
                </div>
                <ul className="mt-5 space-y-3">
                    {previewDistricts.map((district) => (
                        <li key={district.name}>
                            <div className="mb-1.5 flex justify-between text-xs text-[#E8EAF6]">
                                <span>{district.name}</span>
                            </div>
                            <div className="h-1.5 rounded-full bg-white/10">
                                <div
                                    className="h-1.5 rounded-full bg-[#1E88E5]"
                                    style={{ width: district.width }}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
                <p className="mt-5 text-xs leading-relaxed text-[#E8EAF6]/80">
                    Bar length is layout, not a case count. Live figures appear
                    when facilities file the day's return.
                </p>
            </div>

            <div className="rounded-2xl border border-[#1A237E]/10 bg-[#F8FAFC] p-5 text-[#1A237E]">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-medium tracking-[0.16em] text-[#E53935] uppercase">
                        Today's return
                    </p>
                    <p className="text-xs text-[#1A237E]/60">One facility</p>
                </div>
                <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    {[
                        'Suspects',
                        'Tested',
                        'Positive',
                        'Started treatment',
                    ].map((label) => (
                        <div
                            key={label}
                            className="flex items-baseline justify-between border-b border-[#1A237E]/10 pb-2"
                        >
                            <dt className="text-[#1A237E]/75">{label}</dt>
                            <dd className="text-[#1A237E]">No data yet</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

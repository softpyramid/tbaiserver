import { Head, Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';
import type { RouteDefinition } from '@/wayfinder';

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
        detail: 'Who was lost after treatment began.',
    },
] as const;

const rooms = [
    {
        kicker: 'In the lab',
        title: 'Scope, snap, upload.',
        body: 'A technician photographs the smear on the microscope already on the bench. TB-AI reads acid-fast bacilli and returns a result while the patient is still there. No new analyser.',
        points: [
            'Standard microscope, mid-range phone',
            'Result in minutes, not a two-day queue',
            'The microscopist stays in the decision',
        ],
    },
    {
        kicker: 'At the programme',
        title: 'One register for the day.',
        body: 'Each facility files one return for one date: the same counts as the paper register. Public, private, and PPM sit in one view, from the facility up to the district.',
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
    const [menuOpen, setMenuOpen] = useState(false);

    function closeMenu() {
        setMenuOpen(false);
    }

    return (
        <>
            <Head title="TB-AI">
                <meta
                    head-key="description"
                    name="description"
                    content="TB-AI reads a tuberculosis smear from a phone on a standard microscope, and keeps the daily register a programme uses to see testing, treatment, and delay."
                />
            </Head>

            <div className="min-h-screen scroll-smooth bg-[#F8FAFC] text-[#1A237E] dark:bg-[#070B14] dark:text-[#E7E9F2]">
                <header className="sticky top-0 z-40 border-b border-white/10 bg-[#1A237E]/95 text-[#F4F1EA] backdrop-blur-md">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                        <a
                            href="#top"
                            className="flex items-center gap-2.5 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                        >
                            <Mark />
                            <span className="text-sm font-semibold tracking-[0.18em]">
                                TB-AI
                            </span>
                        </a>

                        <nav
                            aria-label="Page"
                            className="hidden items-center gap-8 text-sm text-[#D5D8E6] md:flex"
                        >
                            <a
                                href="#pathway"
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                            >
                                Pathway
                            </a>
                            <a
                                href="#platform"
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                            >
                                Platform
                            </a>
                            <a
                                href="#register"
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                            >
                                Daily return
                            </a>
                        </nav>

                        <div className="hidden items-center gap-3 md:flex">
                            <AuthActions
                                signedIn={signedIn}
                                dashboardUrl={dashboardUrl}
                                tone="dark"
                            />
                        </div>

                        <button
                            type="button"
                            className="inline-flex size-11 items-center justify-center rounded-md text-[#F4F1EA] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E4C98A] md:hidden"
                            aria-expanded={menuOpen}
                            aria-controls="mobile-nav"
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            <span className="sr-only">
                                {menuOpen ? 'Close menu' : 'Open menu'}
                            </span>
                            {menuOpen ? (
                                <X className="size-5" />
                            ) : (
                                <Menu className="size-5" />
                            )}
                        </button>
                    </div>

                    {menuOpen && (
                        <div
                            id="mobile-nav"
                            className="border-t border-white/10 px-5 py-4 md:hidden"
                        >
                            <nav
                                aria-label="Page"
                                className="flex flex-col gap-1 text-sm"
                            >
                                <a
                                    href="#pathway"
                                    onClick={closeMenu}
                                    className="rounded-md px-2 py-3 hover:bg-white/10"
                                >
                                    Pathway
                                </a>
                                <a
                                    href="#platform"
                                    onClick={closeMenu}
                                    className="rounded-md px-2 py-3 hover:bg-white/10"
                                >
                                    Platform
                                </a>
                                <a
                                    href="#register"
                                    onClick={closeMenu}
                                    className="rounded-md px-2 py-3 hover:bg-white/10"
                                >
                                    Daily return
                                </a>
                            </nav>
                            <div className="mt-3 flex flex-col gap-2">
                                <AuthActions
                                    signedIn={signedIn}
                                    dashboardUrl={dashboardUrl}
                                    tone="dark"
                                    stacked
                                />
                            </div>
                        </div>
                    )}
                </header>

                <main id="top">
                    <section className="bg-[#1A237E] text-[#F4F1EA]">
                        <div className="mx-auto grid max-w-6xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:py-24">
                            <div>
                                <p className="text-xs font-medium tracking-[0.22em] text-[#E4C98A] uppercase">
                                    Tuberculosis diagnostics
                                </p>
                                <h1 className="mt-5 max-w-xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.4rem]">
                                    A smear in minutes. The province, the same
                                    day.
                                </h1>
                                <p className="mt-6 max-w-lg text-base leading-relaxed text-[#C9CEDD] sm:text-lg">
                                    TB-AI photographs a smear on the microscope
                                    a lab already owns, reads it, and keeps the
                                    daily register behind that work. Facilities
                                    file the counts. The programme sees testing,
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
                                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase dark:text-[#B7B4F0]">
                                    The pathway
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    Five counts. The whole diagnostic day.
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#3E4C66] dark:text-[#C5CAD8]">
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
                                        className="bg-[#F8FAFC] p-5 dark:bg-[#0C1220]"
                                    >
                                        <span className="font-mono text-xs text-[#E53935] dark:text-[#B7B4F0]">
                                            {item.step}
                                        </span>
                                        <h3 className="mt-3 text-lg font-semibold">
                                            {item.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-relaxed text-[#3E4C66] dark:text-[#C5CAD8]">
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
                                    <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase dark:text-[#B7B4F0]">
                                        {room.kicker}
                                    </p>
                                    <h2 className="mt-3 text-3xl font-semibold tracking-tight">
                                        {room.title}
                                    </h2>
                                    <p className="mt-4 text-base leading-relaxed text-[#3E4C66] dark:text-[#C5CAD8]">
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
                                                    className="mt-2 size-1.5 shrink-0 rounded-full bg-[#E53935] dark:bg-[#B7B4F0]"
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
                                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase dark:text-[#B7B4F0]">
                                    The daily return
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    What a facility files before the day is
                                    over.
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#3E4C66] dark:text-[#C5CAD8]">
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
                                        <span className="font-mono text-xs text-[#E53935] dark:text-[#B7B4F0]">
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

                    <section className="bg-[#1A237E] text-[#F4F1EA]">
                        <div className="mx-auto flex max-w-6xl flex-col items-start justify-between gap-8 px-5 py-16 sm:px-8 lg:flex-row lg:items-center lg:py-20">
                            <div className="max-w-xl">
                                <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    Open the register.
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#C9CEDD]">
                                    Sign in to file today’s return, or to read
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

                <footer className="bg-[#0C1222] text-[#C9CEDD]">
                    <div className="mx-auto flex max-w-6xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-end md:justify-between">
                        <div>
                            <div className="flex items-center gap-2.5 text-[#F4F1EA]">
                                <Mark />
                                <span className="text-sm font-semibold tracking-[0.18em]">
                                    TB-AI
                                </span>
                            </div>
                            <p className="mt-4 max-w-sm text-sm leading-relaxed">
                                Point-of-care tuberculosis diagnostics, and the
                                daily register behind them.
                            </p>
                        </div>
                        <address className="text-sm leading-7 not-italic">
                            <a
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                                href="mailto:contact-us@dhub-ai.info"
                            >
                                contact-us@dhub-ai.info
                            </a>
                            <br />
                            <a
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                                href="https://www.tb-ai.org/"
                                target="_blank"
                                rel="noreferrer"
                            >
                                tb-ai.org
                            </a>
                            <br />
                            <a
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#E4C98A]"
                                href="https://www.linkedin.com/company/tb-ai/"
                                target="_blank"
                                rel="noreferrer"
                            >
                                LinkedIn
                            </a>
                        </address>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Mark() {
    return <img src="/logo.png" alt="" className="size-8" />;
}

function AuthActions({
    signedIn,
    dashboardUrl,
    tone,
    stacked = false,
}: {
    signedIn: boolean;
    dashboardUrl: string | RouteDefinition<'get'>;
    tone: 'dark' | 'hero';
    stacked?: boolean;
}) {
    const primary =
        tone === 'hero'
            ? 'inline-flex h-11 items-center justify-center rounded-md bg-[#F4F1EA] px-5 text-sm font-medium text-[#1A237E] hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E4C98A]'
            : 'inline-flex h-9 items-center justify-center rounded-md bg-[#F4F1EA] px-4 text-sm font-medium text-[#1A237E] hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E4C98A]';

    const secondary =
        tone === 'hero'
            ? 'inline-flex h-11 items-center justify-center rounded-md border border-white/25 px-5 text-sm font-medium text-[#F4F1EA] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E4C98A]'
            : 'inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium text-[#D5D8E6] hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E4C98A]';

    if (signedIn) {
        return (
            <Link href={dashboardUrl} className={primary}>
                Dashboard
            </Link>
        );
    }

    return (
        <div
            className={
                stacked ? 'flex flex-col gap-2' : 'flex items-center gap-2'
            }
        >
            <Link href={login()} className={secondary}>
                Sign in
            </Link>
            <Link href={register()} className={primary}>
                Create account
            </Link>
        </div>
    );
}

function Preview() {
    return (
        <div className="grid gap-4">
            <div className="rounded-2xl border border-white/10 bg-[#18233A] p-5 shadow-[0_30px_80px_-40px_rgba(0,0,0,0.8)]">
                <div className="flex items-center justify-between gap-4">
                    <p className="text-xs font-medium tracking-[0.16em] text-[#E4C98A] uppercase">
                        Provincial view
                    </p>
                    <p className="text-xs text-[#9AA3BA]">Schematic</p>
                </div>
                <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {['Suspects', 'Tested', 'Positive', 'Turnaround'].map(
                        (label) => (
                            <div
                                key={label}
                                className="rounded-lg bg-white/5 px-3 py-3"
                            >
                                <p className="text-[11px] tracking-wide text-[#9AA3BA] uppercase">
                                    {label}
                                </p>
                                <p className="mt-2 font-mono text-sm text-[#F4F1EA]">
                                    —
                                </p>
                            </div>
                        ),
                    )}
                </div>
                <ul className="mt-5 space-y-3">
                    {previewDistricts.map((district) => (
                        <li key={district.name}>
                            <div className="mb-1.5 flex justify-between text-xs text-[#C9CEDD]">
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
                <p className="mt-5 text-xs leading-relaxed text-[#9AA3BA]">
                    Bar length is layout, not a case count. Live figures appear
                    when facilities file the day’s return.
                </p>
            </div>

            <div className="rounded-2xl border border-[#1A237E]/10 bg-[#F7F4EE] p-5 text-[#1A237E]">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-medium tracking-[0.16em] text-[#E53935] uppercase">
                        Today’s return
                    </p>
                    <p className="text-xs text-[#5C6B84]">One facility</p>
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
                            <dt className="text-[#3E4C66]">{label}</dt>
                            <dd className="font-mono text-[#1A237E]">—</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

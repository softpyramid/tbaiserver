import { Head, usePage } from '@inertiajs/react';
import {
    Cloud,
    Microscope,
    MonitorSmartphone,
    Stethoscope,
    type LucideIcon,
} from 'lucide-react';
import { AuthActions, MarketingShell } from '@/components/marketing-shell';

type FeatureTile = {
    title: string;
    body: string;
};

type Testimonial = {
    quote: string;
    name: string;
    role: string;
};

type WelcomeProps = {
    featureTiles: FeatureTile[];
    testimonials: Testimonial[];
    supporters: string[];
};

const services: {
    title: string;
    body: string;
    icon: LucideIcon;
}[] = [
    {
        title: 'TB Diagnosis',
        body: 'AI-assisted smear microscopy analysis under development, to support the microscopist.',
        icon: Microscope,
    },
    {
        title: 'AI Diagnostic Platform',
        body: 'Cloud-based platform in development: upload slides from a smartphone and review AI-assisted results.',
        icon: Cloud,
    },
    {
        title: 'Lab Integration',
        body: 'Designed to work with existing lab infrastructure and staff training.',
        icon: MonitorSmartphone,
    },
    {
        title: 'Telehealth',
        body: 'Remote expert consultation linking rural patients to specialist physicians nationwide.',
        icon: Stethoscope,
    },
];

const previewDistricts = [
    { name: 'Karachi West', width: '86%' },
    { name: 'Tharparkar', width: '64%' },
    { name: 'Larkana', width: '52%' },
    { name: 'Thatta', width: '41%' },
    { name: 'Badin', width: '33%' },
] as const;

export default function Welcome({
    featureTiles,
    testimonials,
    supporters,
}: WelcomeProps) {
    const page = usePage();
    const { auth, tbai } = page.props;
    const signedIn = Boolean(auth.user);
    const dashboardUrl =
        typeof page.props.homeUrl === 'string' ? page.props.homeUrl : '/';
    const visibleServices = tbai.showTelehealth
        ? services
        : services.filter((service) => service.title !== 'Telehealth');

    return (
        <>
            <Head title="" />

            <MarketingShell>
                <main id="top">
                    <section className="bg-[linear-gradient(135deg,#14294c_0%,#1a365d_50%,#29269b_100%)] text-[#F8FAFC]">
                        <div className="mx-auto grid max-w-[1200px] items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] lg:py-24">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.22em] text-electric uppercase">
                                    AI-powered tuberculosis diagnostics
                                </p>
                                <h1 className="mt-5 max-w-xl font-serif text-4xl leading-[1.1] font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                    Saving A Life Every 20 Seconds.
                                </h1>
                                <p className="mt-6 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">
                                    TB-AI is a smartphone-based platform in
                                    development that aims to support rapid,
                                    affordable tuberculosis microscopy and give
                                    programmes a daily TB register.
                                </p>
                                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                    <AuthActions
                                        signedIn={signedIn}
                                        dashboardUrl={dashboardUrl}
                                        tone="hero"
                                    />
                                </div>
                                <p className="mt-4 text-sm text-white/70">
                                    Decision support in development. Not a
                                    diagnostic device.
                                </p>
                            </div>
                            <div className="flex justify-center">
                                <img
                                    src="/logo.png"
                                    alt="TB-AI"
                                    className="w-full max-w-sm rounded-2xl shadow-[0_30px_80px_-40px_rgba(0,0,0,0.8)]"
                                />
                            </div>
                        </div>
                    </section>

                    <section
                        id="mission"
                        className="scroll-mt-20 border-b border-navy/10 dark:border-white/10"
                    >
                        <div className="mx-auto grid max-w-[1200px] gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:py-20">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.22em] text-brand uppercase dark:text-indigo-300">
                                    About TB-AI
                                </p>
                                <h2 className="mt-3 font-serif text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                    Equitable Healthcare Through Digital
                                    Diagnostics
                                </h2>
                                <p className="mt-4 max-w-xl text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                    TB-AI is a pioneering healthtech startup
                                    that harnesses artificial intelligence to
                                    make tuberculosis diagnosis rapid,
                                    cost-effective, and accessible, even in the
                                    most resource-limited settings.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                <article className="rounded-2xl border border-navy/10 bg-white p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)] dark:border-white/10 dark:bg-navy">
                                    <h3 className="font-serif text-xl font-bold">
                                        Our Mission
                                    </h3>
                                    <p className="mt-2 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                        Equitable healthcare through digital
                                        diagnostics.
                                    </p>
                                </article>
                                <article className="rounded-2xl border border-navy/10 bg-white p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)] dark:border-white/10 dark:bg-navy">
                                    <h3 className="font-serif text-xl font-bold">
                                        Our Vision
                                    </h3>
                                    <p className="mt-2 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                        Accuracy, affordability, access, speed.
                                    </p>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section
                        id="services"
                        className="scroll-mt-20 border-b border-navy/10 bg-[#EEF2F7] dark:border-white/10 dark:bg-[#0b1220]"
                    >
                        <div className="mx-auto max-w-[1200px] px-5 py-16 sm:px-8 lg:py-20">
                            <div className="max-w-2xl">
                                <p className="text-xs font-semibold tracking-[0.22em] text-brand uppercase dark:text-indigo-300">
                                    What we do
                                </p>
                                <h2 className="mt-3 font-serif text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                    Our Diagnostic Services
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                    Comprehensive AI-powered diagnostics
                                    accessible from a smartphone, anywhere in
                                    the world.
                                </p>
                            </div>
                            <ul className="mt-10 grid gap-4 sm:grid-cols-2">
                                {visibleServices.map((service) => {
                                    const Icon = service.icon;

                                    return (
                                        <li
                                            key={service.title}
                                            className="rounded-2xl border border-navy/10 bg-white p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)] dark:border-white/10 dark:bg-navy"
                                        >
                                            <Icon
                                                aria-hidden
                                                className="size-6 text-brand"
                                            />
                                            <h3 className="mt-4 font-serif text-xl font-bold">
                                                {service.title}
                                            </h3>
                                            <p className="mt-2 text-sm leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                                {service.body}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    </section>

                    <section
                        id="register"
                        className="scroll-mt-20 border-b border-navy/10 dark:border-white/10"
                    >
                        <div className="mx-auto grid max-w-[1200px] items-start gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:py-20">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.22em] text-brand uppercase dark:text-indigo-300">
                                    For labs and programmes
                                </p>
                                <h2 className="mt-3 font-serif text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                    TB-AI Register and Dashboard
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                    Labs and programmes keep a daily TB
                                    register. Each facility files one return for
                                    one date: suspects, people tested, and
                                    positive results. Positivity rate and
                                    turnaround time are read from that return. A
                                    heat map shows where the pathway sits across
                                    districts.
                                </p>
                            </div>
                            <Preview />
                        </div>
                    </section>

                    <section
                        id="why"
                        className="scroll-mt-20 border-b border-navy/10 bg-[#EEF2F7] dark:border-white/10 dark:bg-[#0b1220]"
                    >
                        <div className="mx-auto max-w-[1200px] px-5 py-16 sm:px-8 lg:py-20">
                            <div className="max-w-2xl">
                                <p className="text-xs font-semibold tracking-[0.22em] text-brand uppercase dark:text-indigo-300">
                                    Why choose us
                                </p>
                                <h2 className="mt-3 font-serif text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                    Empowering Health Care Systems
                                    Internationally
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                    TB-AI bridges the gap between
                                    state-of-the-art AI technology and the field
                                    clinics that need it most. Our system is
                                    designed to run on existing equipment
                                    without expensive modifications.
                                </p>
                            </div>
                            <ul
                                className={`mt-10 grid gap-4 sm:grid-cols-2 ${
                                    featureTiles.length > 3
                                        ? 'lg:grid-cols-4'
                                        : 'lg:grid-cols-3'
                                }`}
                            >
                                {featureTiles.map((tile) => (
                                    <li
                                        key={tile.title}
                                        className="rounded-2xl border border-navy/10 bg-white p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)] dark:border-white/10 dark:bg-navy"
                                    >
                                        <h3 className="font-serif text-xl font-bold">
                                            {tile.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                            {tile.body}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>

                    {supporters.length > 0 && (
                        <section
                            aria-label="Supported and recognized by leading global organizations"
                            className="border-b border-navy/10 dark:border-white/10"
                        >
                            <div className="mx-auto max-w-[1200px] px-5 py-12 sm:px-8">
                                <h2 className="text-center text-xs font-semibold tracking-[0.18em] text-[#64748B] uppercase">
                                    Supported and recognized by leading global
                                    organizations
                                </h2>
                                <div className="mt-6 overflow-hidden">
                                    <div className="supporter-track flex w-max gap-10">
                                        {[0, 1].map((copy) => (
                                            <ul
                                                key={copy}
                                                aria-hidden={copy === 1}
                                                className="flex gap-10"
                                            >
                                                {supporters.map((name) => (
                                                    <li
                                                        key={`${copy}-${name}`}
                                                        className="text-sm font-semibold tracking-wide whitespace-nowrap text-navy/70 dark:text-white/70"
                                                    >
                                                        {name}
                                                    </li>
                                                ))}
                                            </ul>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {testimonials.length > 0 && (
                        <section className="border-b border-navy/10 dark:border-white/10">
                            <div className="mx-auto max-w-[1200px] px-5 py-16 sm:px-8 lg:py-20">
                                <div className="max-w-2xl">
                                    <p className="text-xs font-semibold tracking-[0.22em] text-brand uppercase dark:text-indigo-300">
                                        Testimonials
                                    </p>
                                    <h2 className="mt-3 font-serif text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                        What Clinical Leaders Say
                                    </h2>
                                    <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                        Read how our platform helps speed up
                                        workflows and increase screen rates in
                                        pilot hospitals.
                                    </p>
                                </div>
                                <ul className="mt-10 grid gap-4 lg:grid-cols-3">
                                    {testimonials.map((item) => (
                                        <li
                                            key={item.name}
                                            className="rounded-2xl border border-navy/10 bg-white p-6 shadow-[0_2px_20px_rgba(0,0,0,0.06)] dark:border-white/10 dark:bg-navy"
                                        >
                                            <blockquote className="text-sm leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                                “{item.quote}”
                                            </blockquote>
                                            <p className="mt-4 text-sm font-semibold">
                                                {item.name}
                                            </p>
                                            <p className="text-xs text-[#64748B] dark:text-white/60">
                                                {item.role}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </section>
                    )}

                    <section className="bg-[linear-gradient(135deg,#29269b_0%,#14294c_100%)] text-[#F8FAFC]">
                        <div className="mx-auto flex max-w-[1200px] flex-col items-start justify-between gap-8 px-5 py-16 sm:px-8 lg:flex-row lg:items-center lg:py-20">
                            <div className="max-w-xl">
                                <h2 className="font-serif text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                    Interested in TB-AI for your facility or
                                    programme?
                                </h2>
                                <p className="mt-4 text-base leading-relaxed text-white/85">
                                    Request access and we will be in touch.
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
            <div className="rounded-2xl border border-white/10 bg-navy p-5 text-[#F8FAFC] shadow-[0_30px_80px_-40px_rgba(0,0,0,0.8)]">
                <div className="flex items-center justify-between gap-4">
                    <p className="text-xs font-medium tracking-[0.16em] uppercase">
                        Provincial view
                    </p>
                    <p className="text-xs text-white/70">Preview</p>
                </div>
                <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {[
                        'Suspects',
                        'Tested',
                        'Positive',
                        'Positivity rate',
                        'Turnaround time',
                    ].map((label) => (
                        <div
                            key={label}
                            className="rounded-lg bg-white/5 px-3 py-3"
                        >
                            <p className="text-[11px] tracking-wide text-white/70 uppercase">
                                {label}
                            </p>
                            <p className="mt-2 text-sm">No data yet</p>
                        </div>
                    ))}
                </div>
                <p className="mt-5 text-xs font-medium tracking-[0.16em] text-white/70 uppercase">
                    Heat map
                </p>
                <ul className="mt-3 space-y-3">
                    {previewDistricts.map((district) => (
                        <li key={district.name}>
                            <div className="mb-1.5 flex justify-between text-xs text-[#E2E8F0]">
                                <span>{district.name}</span>
                            </div>
                            <div className="h-1.5 rounded-full bg-white/10">
                                <div
                                    className="h-1.5 rounded-full bg-electric"
                                    style={{ width: district.width }}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
                <p className="mt-5 text-xs leading-relaxed text-white/70">
                    Bar length is layout, not a case count. Live figures appear
                    when facilities file the day's return.
                </p>
            </div>

            <div className="rounded-2xl border border-navy/10 bg-[#F8FAFC] p-5 text-navy">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-medium tracking-[0.16em] text-brand uppercase">
                        Today's return
                    </p>
                    <p className="text-xs text-navy/60">Preview</p>
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
                            className="flex items-baseline justify-between border-b border-navy/10 pb-2"
                        >
                            <dt className="text-navy/75">{label}</dt>
                            <dd>No data yet</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

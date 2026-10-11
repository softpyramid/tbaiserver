import { ImpersonationBanner } from '@/components/impersonation-banner';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SoftPyramidCredit } from '@/components/softpyramid-credit';
import { home, login, privacy, terms } from '@/routes';
import { create as requestAccess } from '@/routes/access-requests';
import type { RouteDefinition } from '@/wayfinder';

export const developmentNotice =
    'TB-AI is in development. It is decision support, not a diagnostic device. The microscopist makes the final call.';

export const contactName = 'Dr. Hira Fareed';
export const contactEmail = 'dochirafarid@gmail.com';

const pageLinks = [
    { href: '/#mission', label: 'Mission' },
    { href: '/#services', label: 'Services' },
    { href: '/#register', label: 'Register' },
] as const;

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric';

export function MarketingShell({ children }: { children: ReactNode }) {
    const page = usePage();
    const { auth, tbai } = page.props;
    const signedIn = Boolean(auth.user);
    const dashboardUrl =
        typeof page.props.homeUrl === 'string' ? page.props.homeUrl : '/';
    const onRequestAccess =
        page.url === '/request-access' ||
        page.url.startsWith('/request-access?');
    const [menuOpen, setMenuOpen] = useState(false);

    function closeMenu() {
        setMenuOpen(false);
    }

    return (
        <>
            <ImpersonationBanner />
            <div className="min-h-screen scroll-smooth bg-[#F8FAFC] font-sans text-navy dark:bg-[#0b1220] dark:text-[#F8FAFC]">
                <header className="sticky top-0 z-40 border-b border-white/10 bg-navy/95 text-[#F8FAFC] backdrop-blur-md">
                    <div className="mx-auto flex h-[4.5rem] max-w-[1200px] items-center justify-between px-5 sm:px-8">
                        <Link
                            href="/"
                            className={`flex items-center gap-2.5 rounded-sm ${focusRing}`}
                        >
                            <Mark />
                            <span className="flex flex-col leading-none">
                                <span className="font-serif text-lg font-bold tracking-tight">
                                    TB-AI
                                </span>
                                <span className="text-[9px] font-semibold tracking-[0.22em] text-white/70">
                                    DIAGNOSTICS
                                </span>
                            </span>
                        </Link>

                        <nav
                            aria-label="Page"
                            className="hidden items-center gap-8 text-sm text-white/80 md:flex"
                        >
                            {pageLinks.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className={`hover:text-white ${focusRing}`}
                                >
                                    {item.label}
                                </a>
                            ))}
                        </nav>

                        <div className="hidden items-center gap-3 md:flex">
                            <AuthActions
                                signedIn={signedIn}
                                dashboardUrl={dashboardUrl}
                                tone="dark"
                                backHome={onRequestAccess}
                            />
                        </div>

                        <button
                            type="button"
                            className="inline-flex size-11 items-center justify-center rounded-md text-[#F8FAFC] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric md:hidden"
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
                                {pageLinks.map((item) => (
                                    <a
                                        key={item.href}
                                        href={item.href}
                                        onClick={closeMenu}
                                        className="rounded-md px-2 py-3 hover:bg-white/10"
                                    >
                                        {item.label}
                                    </a>
                                ))}
                            </nav>
                            <div className="mt-3 flex flex-col gap-2">
                                <AuthActions
                                    signedIn={signedIn}
                                    dashboardUrl={dashboardUrl}
                                    tone="dark"
                                    stacked
                                    backHome={onRequestAccess}
                                />
                            </div>
                        </div>
                    )}
                </header>

                {children}

                <footer className="bg-navy text-[#E2E8F0]">
                    <div className="mx-auto grid max-w-[1200px] gap-10 px-5 py-12 sm:px-8 md:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)]">
                        <div>
                            <div className="flex items-center gap-2.5 text-[#F8FAFC]">
                                <Mark />
                                <span className="flex flex-col leading-none">
                                    <span className="font-serif text-lg font-bold">
                                        TB-AI
                                    </span>
                                    <span className="text-[9px] font-semibold tracking-[0.22em] text-white/70">
                                        DIAGNOSTICS
                                    </span>
                                </span>
                            </div>
                            <p className="mt-4 max-w-sm text-sm leading-relaxed">
                                Saving a life every 20 seconds through
                                AI-powered point-of-care diagnostics.
                            </p>
                            {tbai.developmentNotice && (
                                <p className="mt-4 max-w-md text-sm leading-relaxed text-[#F8FAFC]">
                                    {tbai.developmentNotice}
                                </p>
                            )}
                            {tbai.partnerLine && (
                                <SoftPyramidCredit
                                    variant="partner"
                                    className="mt-4 text-sm text-[#F8FAFC]"
                                />
                            )}
                        </div>
                        <div className="text-sm leading-7">
                            <nav aria-label="Legal" className="mb-3 flex gap-4">
                                <Link
                                    href={privacy()}
                                    className={`hover:text-white ${focusRing}`}
                                >
                                    Privacy
                                </Link>
                                <Link
                                    href={terms()}
                                    className={`hover:text-white ${focusRing}`}
                                >
                                    Terms
                                </Link>
                            </nav>
                            <address className="not-italic">
                                {tbai.contact.location}
                                <br />
                                <a
                                    className={`hover:text-white ${focusRing}`}
                                    href={`mailto:${tbai.contact.email}`}
                                >
                                    {tbai.contact.email}
                                </a>
                                <br />
                                {tbai.contact.reach}
                            </address>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Mark() {
    return (
        <AppLogoIcon alt="" className="size-10 rounded-[4px] object-cover" />
    );
}

export function AuthActions({
    signedIn,
    dashboardUrl,
    tone,
    stacked = false,
    backHome = false,
}: {
    signedIn: boolean;
    dashboardUrl: string | RouteDefinition<'get'>;
    tone: 'dark' | 'hero';
    stacked?: boolean;
    backHome?: boolean;
}) {
    const primary =
        tone === 'hero'
            ? 'inline-flex h-11 items-center justify-center rounded-md bg-brand px-5 text-sm font-semibold text-white hover:bg-[#211e7a] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric'
            : 'inline-flex h-9 items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-white hover:bg-[#211e7a] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric';

    const secondary =
        tone === 'hero'
            ? 'inline-flex h-11 items-center justify-center rounded-md border border-white/40 px-5 text-sm font-semibold text-[#F8FAFC] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric'
            : 'inline-flex h-9 items-center justify-center rounded-md border border-white/30 px-3 text-sm font-semibold text-[#F8FAFC] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric';

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
            {backHome ? (
                <Link
                    href={home()}
                    className={`inline-flex items-center text-sm font-medium text-[#E2E8F0] underline-offset-4 hover:text-white hover:underline ${focusRing}`}
                >
                    Back to home
                </Link>
            ) : (
                <Link href={requestAccess()} className={primary}>
                    Request access
                </Link>
            )}
        </div>
    );
}

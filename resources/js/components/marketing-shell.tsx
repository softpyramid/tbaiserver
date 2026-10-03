import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, home, login, privacy, terms } from '@/routes';
import { create as requestAccess } from '@/routes/access-requests';
import type { RouteDefinition } from '@/wayfinder';

export const developmentNotice =
    'TB-AI is in development. It is decision support, not a diagnostic device. The microscopist makes the final call.';

export const contactName = 'Dr. Hira Fareed';
export const contactEmail = 'dochirafarid@gmail.com';

export function MarketingShell({ children }: { children: ReactNode }) {
    const page = usePage();
    const { auth, currentTeam } = page.props;
    const signedIn = Boolean(auth.user);
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';
    const onRequestAccess =
        page.url === '/request-access' ||
        page.url.startsWith('/request-access?');
    const [menuOpen, setMenuOpen] = useState(false);

    function closeMenu() {
        setMenuOpen(false);
    }

    return (
        <div className="min-h-screen scroll-smooth bg-[#F8FAFC] text-[#1A237E] dark:bg-[#0B1028] dark:text-[#F8FAFC]">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-[#1A237E]/95 text-[#F8FAFC] backdrop-blur-md">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                    >
                        <Mark />
                        <span className="text-sm font-semibold tracking-[0.18em]">
                            TB-AI
                        </span>
                    </Link>

                    <nav
                        aria-label="Page"
                        className="hidden items-center gap-8 text-sm text-[#E8EAF6] md:flex"
                    >
                        <a
                            href="/#pathway"
                            className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                        >
                            Pathway
                        </a>
                        <a
                            href="/#platform"
                            className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                        >
                            Platform
                        </a>
                        <a
                            href="/#register"
                            className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                        >
                            Daily return
                        </a>
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
                        className="inline-flex size-11 items-center justify-center rounded-md text-[#F8FAFC] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E88E5] md:hidden"
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
                                href="/#pathway"
                                onClick={closeMenu}
                                className="rounded-md px-2 py-3 hover:bg-white/10"
                            >
                                Pathway
                            </a>
                            <a
                                href="/#platform"
                                onClick={closeMenu}
                                className="rounded-md px-2 py-3 hover:bg-white/10"
                            >
                                Platform
                            </a>
                            <a
                                href="/#register"
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
                                backHome={onRequestAccess}
                            />
                        </div>
                    </div>
                )}
            </header>

            {children}

            <footer className="bg-[#121858] text-[#E8EAF6]">
                <div className="mx-auto flex max-w-6xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5 text-[#F8FAFC]">
                            <Mark />
                            <span className="text-sm font-semibold tracking-[0.18em]">
                                TB-AI
                            </span>
                        </div>
                        <p className="mt-4 max-w-sm text-sm leading-relaxed">
                            Decision support in development, and the daily
                            register a programme keeps.
                        </p>
                        <p className="mt-4 max-w-sm text-sm leading-relaxed text-[#F8FAFC]">
                            {developmentNotice}
                        </p>
                    </div>
                    <div className="text-sm leading-7">
                        <nav aria-label="Legal" className="mb-3 flex gap-4">
                            <Link
                                href={privacy()}
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                            >
                                Privacy
                            </Link>
                            <Link
                                href={terms()}
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                            >
                                Terms
                            </Link>
                        </nav>
                        <address className="not-italic">
                            {contactName}
                            <br />
                            <a
                                className="hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
                                href={`mailto:${contactEmail}`}
                            >
                                {contactEmail}
                            </a>
                        </address>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function Mark() {
    return <AppLogoIcon alt="" className="size-8" />;
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
            ? 'inline-flex h-11 items-center justify-center rounded-md bg-[#E53935] px-5 text-sm font-medium text-white hover:bg-[#D32F2F] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E88E5]'
            : 'inline-flex h-9 items-center justify-center rounded-md bg-[#E53935] px-4 text-sm font-medium text-white hover:bg-[#D32F2F] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E88E5]';

    const secondary =
        tone === 'hero'
            ? 'inline-flex h-11 items-center justify-center rounded-md border border-[#1E88E5] px-5 text-sm font-medium text-[#F8FAFC] hover:bg-[#1E88E5]/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E88E5]'
            : 'inline-flex h-9 items-center justify-center rounded-md border border-[#1E88E5] px-3 text-sm font-medium text-[#F8FAFC] hover:bg-[#1E88E5]/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E88E5]';

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
                    className="inline-flex items-center text-sm font-medium text-[#E8EAF6] underline-offset-4 hover:text-white hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E88E5]"
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

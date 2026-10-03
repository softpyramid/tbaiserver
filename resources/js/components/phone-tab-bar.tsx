import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Building2,
    CircleUser,
    ClipboardList,
    ClipboardPlus,
    Ellipsis,
    FileText,
    Globe,
    Hospital,
    LayoutDashboard,
    LogOut,
    Map,
    ScrollText,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { clearClientSession } from '@/lib/sign-out';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';

const icons: Record<string, LucideIcon> = {
    register: ClipboardList,
    returns: FileText,
    screening: ClipboardPlus,
    facility: Building2,
    account: CircleUser,
    overview: LayoutDashboard,
    registered: Hospital,
    map: Map,
    alerts: Bell,
    facilities: Hospital,
    people: Users,
    more: Ellipsis,
    daily_returns: FileText,
    audit_log: ScrollText,
};

/**
 * Bottom tabs for phone widths. The server decides the tabs for each role and
 * which one owns the page, so this only draws them. A tab with the href "#more"
 * opens a sheet with the rest of the links instead of going to a page.
 */
export function PhoneTabBar() {
    const { nav } = usePage().props;
    const [moreOpen, setMoreOpen] = useState(false);
    const tabs = nav.tabs;

    if (tabs.length === 0) {
        return null;
    }

    return (
        <>
            <nav
                aria-label={nav.tabsLabel}
                data-test="phone-tabs"
                className="fixed inset-x-0 bottom-0 z-40 border-t bg-background md:hidden"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                <ul
                    className="grid min-h-14"
                    style={{
                        gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))`,
                    }}
                >
                    {tabs.map((tab) => {
                        const Icon = icons[tab.icon] ?? Globe;
                        const className = cn(
                            'flex min-h-14 w-full flex-col items-center justify-center gap-0.5 px-0.5 py-1 text-center text-[13px] leading-tight text-foreground',
                            'focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none focus-visible:ring-inset',
                            tab.current
                                ? 'bg-muted font-semibold'
                                : 'font-medium',
                        );
                        const content = (
                            <>
                                <Icon
                                    aria-hidden="true"
                                    className="size-6 shrink-0"
                                />
                                <span className="max-w-full truncate">
                                    {tab.label}
                                </span>
                            </>
                        );

                        return (
                            <li key={tab.id} className="min-w-0">
                                {tab.href === '#more' ? (
                                    <button
                                        type="button"
                                        data-test="phone-tab-more"
                                        aria-haspopup="dialog"
                                        aria-current={
                                            tab.current ? 'page' : undefined
                                        }
                                        className={className}
                                        onClick={() => setMoreOpen(true)}
                                    >
                                        {content}
                                    </button>
                                ) : (
                                    <Link
                                        href={tab.href}
                                        prefetch
                                        data-test={`phone-tab-${tab.id}`}
                                        aria-current={
                                            tab.current ? 'page' : undefined
                                        }
                                        className={className}
                                    >
                                        {content}
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="bottom"
                    className="gap-2 rounded-t-xl pb-6"
                    data-test="phone-more-sheet"
                >
                    <SheetHeader>
                        <SheetTitle>{nav.moreLabel}</SheetTitle>
                        <SheetDescription className="sr-only">
                            {nav.moreLabel}
                        </SheetDescription>
                    </SheetHeader>
                    <ul className="flex flex-col px-2">
                        {nav.more.map((link) => {
                            const Icon = icons[link.icon] ?? Globe;

                            return (
                                <li key={link.id}>
                                    <Link
                                        href={link.href}
                                        data-test={`phone-more-${link.id}`}
                                        aria-current={
                                            link.current ? 'page' : undefined
                                        }
                                        onClick={() => setMoreOpen(false)}
                                        className={cn(
                                            'flex min-h-12 items-center gap-3 rounded-md px-3 text-base',
                                            link.current
                                                ? 'bg-muted font-semibold'
                                                : 'font-medium',
                                        )}
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="size-5"
                                        />
                                        {link.label}
                                    </Link>
                                </li>
                            );
                        })}
                        <li>
                            <Link
                                href={logout()}
                                as="button"
                                replace
                                data-test="phone-more-logout"
                                onClick={clearClientSession}
                                className="flex min-h-12 w-full items-center gap-3 rounded-md px-3 text-base font-medium"
                            >
                                <LogOut aria-hidden="true" className="size-5" />
                                {nav.logoutLabel}
                            </Link>
                        </li>
                    </ul>
                </SheetContent>
            </Sheet>
        </>
    );
}

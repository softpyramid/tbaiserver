import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <nav
                aria-label="Settings"
                data-test="settings-tabs"
                className="mb-6 grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 md:hidden"
            >
                {sidebarNavItems.map((item, index) => {
                    const current = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={`tab-${toUrl(item.href)}-${index}`}
                            href={item.href}
                            aria-current={current ? 'page' : undefined}
                            className={cn(
                                'flex min-h-10 items-center justify-center rounded-md px-2 text-center text-sm focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none',
                                current
                                    ? 'bg-background font-semibold text-foreground shadow-sm ring-1 ring-border'
                                    : 'font-medium text-muted-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="hidden w-full max-w-xl md:block lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings list"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 max-md:hidden lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}

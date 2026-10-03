import { Link } from '@inertiajs/react';
import { facilityTabs, isTabActive, useNavProps } from '@/lib/entry-nav';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export function FacilityTabBar() {
    const { t } = useEntryI18n();
    const navProps = useNavProps();
    const tabs = facilityTabs(navProps);

    return (
        <nav
            aria-label={t('nav_register')}
            data-test="facility-tabs"
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
                    const active = isTabActive(tab, navProps.activeTab);
                    const Icon = tab.icon;

                    return (
                        <li key={tab.id} className="min-w-0">
                            <Link
                                href={tab.href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-14 w-full flex-col items-center justify-center gap-0.5 px-1 py-1 text-center text-base leading-tight text-foreground',
                                    'focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none focus-visible:ring-inset',
                                    active
                                        ? 'bg-muted font-semibold'
                                        : 'font-medium',
                                )}
                            >
                                <Icon
                                    aria-hidden="true"
                                    className="size-6 shrink-0"
                                />
                                <span className="max-w-full">
                                    {t(tab.labelKey)}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

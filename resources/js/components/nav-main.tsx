import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

export type NavChild = {
    key: string;
    label: string;
    href: string;
    badge: number | null;
};

export type NavEntry = {
    key: string;
    label: string;
    href: string | null;
    badge: number | null;
    expanded: boolean;
    children: NavChild[];
};

export type NavGroup = {
    key: string;
    label: string;
    items: NavEntry[];
};

const STORAGE_PREFIX = 'tbai.sidebar.expanded.';

export function NavMain({
    groups,
    icons,
}: {
    groups: NavGroup[];
    icons: Record<string, LucideIcon>;
}) {
    const page = usePage();
    const userId = page.props.auth.user?.id;
    const hrefs = groups.flatMap((group) =>
        group.items.flatMap((item) => [
            ...(item.href ? [item.href] : []),
            ...item.children.map((child) => child.href),
        ]),
    );
    const [openMap, setOpenMap] = useState<Record<string, boolean>>(() =>
        initialOpen(userId, groups),
    );
    const activeParents = groups
        .flatMap((group) => group.items)
        .filter((item) => item.expanded)
        .map((item) => item.key)
        .join('|');

    useEffect(() => {
        if (activeParents === '') {
            return;
        }

        const keys = activeParents.split('|');

        setOpenMap((current) => {
            let changed = false;
            const next = { ...current };

            for (const key of keys) {
                if (!next[key]) {
                    next[key] = true;
                    changed = true;
                }
            }

            return changed ? next : current;
        });
    }, [activeParents]);

    useEffect(() => {
        if (userId == null || typeof window === 'undefined') {
            return;
        }

        const keys = Object.entries(openMap)
            .filter(([, isOpen]) => isOpen)
            .map(([key]) => key);

        window.localStorage.setItem(
            `${STORAGE_PREFIX}${userId}`,
            JSON.stringify(keys),
        );
    }, [openMap, userId]);

    if (groups.length === 0) {
        return null;
    }

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.key} className="px-2 py-0">
                    <SidebarGroupLabel className="uppercase tracking-wide">
                        {group.label}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <NavRow
                                key={item.key}
                                item={item}
                                hrefs={hrefs}
                                icon={icons[item.key] ?? null}
                                open={openMap[item.key] ?? item.expanded}
                                onToggle={() =>
                                    setOpenMap((current) => ({
                                        ...current,
                                        [item.key]: !(
                                            current[item.key] ?? item.expanded
                                        ),
                                    }))
                                }
                            />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}

function NavRow({
    item,
    hrefs,
    icon,
    open,
    onToggle,
}: {
    item: NavEntry;
    hrefs: string[];
    icon: LucideIcon | null;
    open: boolean;
    onToggle: () => void;
}) {
    const { isMobile, setOpenMobile, state, setOpen } = useSidebar();
    const page = usePage();
    const Icon = icon;
    const childActive = item.children.some((child) =>
        itemIsActive(child.href, page.url, hrefs),
    );
    const pending = item.children.reduce(
        (total, child) => total + (child.badge ?? 0),
        0,
    );

    const closeMobileDrawer = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };

    if (item.children.length > 0 || item.href === null) {
        const panelId = `nav-children-${item.key}`;

        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    type="button"
                    tooltip={{ children: item.label }}
                    isActive={childActive}
                    aria-expanded={open}
                    aria-controls={panelId}
                    data-test={`nav-parent-${item.key}`}
                    onClick={() => {
                        if (!isMobile && state === 'collapsed') {
                            setOpen(true);
                            if (!open) {
                                onToggle();
                            }

                            return;
                        }

                        onToggle();
                    }}
                >
                    {Icon ? <Icon /> : null}
                    <span>{item.label}</span>
                    {!open && pending > 0 ? <CountBadge count={pending} /> : null}
                    <ChevronRight
                        aria-hidden="true"
                        className={cn(
                            'ml-auto transition-transform duration-200 group-data-[collapsible=icon]:hidden motion-reduce:transition-none',
                            open && 'rotate-90',
                        )}
                    />
                </SidebarMenuButton>
                <div
                    id={panelId}
                    className={cn(
                        'grid transition-[grid-template-rows] duration-200 ease-linear motion-reduce:transition-none',
                        'group-data-[collapsible=icon]:hidden',
                        open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                    )}
                >
                    <div className="overflow-hidden" inert={open ? undefined : true}>
                        <SidebarMenuSub>
                            {item.children.map((child) => {
                                const active = itemIsActive(
                                    child.href,
                                    page.url,
                                    hrefs,
                                );

                                return (
                                    <SidebarMenuSubItem key={child.key}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={active}
                                        >
                                            <Link
                                                href={child.href}
                                                prefetch
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                                data-test={`nav-${child.key}`}
                                                onClick={closeMobileDrawer}
                                            >
                                                <span>{child.label}</span>
                                                {child.badge != null &&
                                                child.badge > 0 ? (
                                                    <CountBadge
                                                        count={child.badge}
                                                    />
                                                ) : null}
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                );
                            })}
                        </SidebarMenuSub>
                    </div>
                </div>
            </SidebarMenuItem>
        );
    }

    const active = item.href
        ? itemIsActive(item.href, page.url, hrefs)
        : false;

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: item.label }}
            >
                <Link
                    href={item.href ?? '/'}
                    prefetch
                    aria-current={active ? 'page' : undefined}
                    data-test={`nav-${item.key}`}
                    onClick={closeMobileDrawer}
                >
                    {Icon ? <Icon /> : null}
                    <span>{item.label}</span>
                    {item.badge != null && item.badge > 0 ? (
                        <CountBadge count={item.badge} />
                    ) : null}
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function CountBadge({ count }: { count: number }) {
    return (
        <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-md bg-sidebar-primary px-1 text-xs font-medium text-sidebar-primary-foreground tabular-nums group-data-[collapsible=icon]:hidden">
            {count}
        </span>
    );
}

function initialOpen(
    userId: number | undefined,
    groups: NavGroup[],
): Record<string, boolean> {
    const stored = readStored(userId);
    const map: Record<string, boolean> = {};

    for (const group of groups) {
        for (const item of group.items) {
            if (item.children.length === 0) {
                continue;
            }

            const remembered = stored?.includes(item.key) ?? false;
            map[item.key] = remembered || item.expanded;
        }
    }

    return map;
}

function readStored(userId: number | undefined): string[] | null {
    if (userId == null || typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(`${STORAGE_PREFIX}${userId}`);

        if (raw === null) {
            return null;
        }

        const parsed: unknown = JSON.parse(raw);

        if (!Array.isArray(parsed)) {
            return null;
        }

        return parsed.filter((item): item is string => typeof item === 'string');
    } catch {
        return null;
    }
}

function pathnameOf(href: string): string {
    const withoutQuery = href.split('?')[0] ?? href;
    const path =
        withoutQuery.startsWith('http://') || withoutQuery.startsWith('https://')
            ? new URL(withoutQuery).pathname
            : withoutQuery;

    if (path.length > 1 && path.endsWith('/')) {
        return path.slice(0, -1);
    }

    return path;
}

function itemIsActive(href: string, currentPath: string, hrefs: string[]): boolean {
    const target = pathnameOf(href);
    const path = pathnameOf(currentPath);
    const matches = path === target || path.startsWith(`${target}/`);

    if (!matches) {
        return false;
    }

    return !hrefs.some((other) => {
        const candidate = pathnameOf(other);

        if (candidate === target || candidate.length <= target.length) {
            return false;
        }

        return path === candidate || path.startsWith(`${candidate}/`);
    });
}

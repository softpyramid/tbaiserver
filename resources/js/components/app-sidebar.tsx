import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    ClipboardList,
    ClipboardPlus,
    Globe,
    History,
    Inbox,
    LayoutGrid,
    List,
    MapPinned,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const icons = {
    command: LayoutGrid,
    register: ClipboardList,
    returns_history: History,
    screening: ClipboardPlus,
    my_facility: Building2,
    facility_profile: Building2,
    team_members: Users,
    facilities: List,
    daily_returns: CalendarDays,
    revisions: History,
    screening_sessions: ClipboardPlus,
    users: Users,
    programme_assignments: MapPinned,
    access_requests: Inbox,
    teams: Users,
} as const;

type NavKey = keyof typeof icons;

function isNavKey(key: string): key is NavKey {
    return key in icons;
}

export function AppSidebar() {
    const page = usePage();
    const { currentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    const closeMobileDrawer = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';
    const groups = page.props.nav.groups ?? [];
    const hrefs = groups.flatMap((group) => group.items.map((item) => item.href));

    const footerNavItems: NavItem[] = [
        {
            title: 'TB-AI',
            href: 'https://www.tb-ai.org/',
            icon: Globe,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={dashboardUrl}
                                prefetch
                                onClick={closeMobileDrawer}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) => (
                    <NavMain
                        key={group.key}
                        label={group.label}
                        items={group.items.map((item) => ({
                            title: item.label,
                            href: item.href,
                            icon: isNavKey(item.key) ? icons[item.key] : null,
                            isActive: itemIsActive(item.href, currentUrl, hrefs),
                        }))}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

function itemIsActive(href: string, currentPath: string, hrefs: string[]): boolean {
    const target = href.split('?')[0] ?? href;
    const path = currentPath.split('?')[0] ?? currentPath;

    if (path === target) {
        return true;
    }

    if (!path.startsWith(`${target}/`)) {
        return false;
    }

    return !hrefs.some((other) => {
        const candidate = other.split('?')[0] ?? other;

        return candidate !== target && (path === candidate || path.startsWith(`${candidate}/`));
    });
}

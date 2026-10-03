import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CircleUser,
    ClipboardList,
    ClipboardPlus,
    Globe,
    History,
    Inbox,
    LayoutGrid,
    List,
    MapPinned,
    Users,
    type LucideIcon,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain, type NavGroup } from '@/components/nav-main';
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
import { about } from '@/routes';
import type { NavItem } from '@/types';

const icons: Record<string, LucideIcon> = {
    command: LayoutGrid,
    registered: List,
    command_map: MapPinned,
    command_alerts: Inbox,
    command_drivers: LayoutGrid,
    command_incidence: LayoutGrid,
    daily_returns: CalendarDays,
    register: ClipboardList,
    returns_history: History,
    screening: ClipboardPlus,
    my_facility: Building2,
    facility_profile: Building2,
    team_members: Users,
    account: CircleUser,
    facilities: List,
    data: CalendarDays,
    people: Users,
    revisions: History,
    screening_sessions: ClipboardPlus,
    users: Users,
    programme_assignments: MapPinned,
    access_requests: Inbox,
    teams: Users,
};

export function AppSidebar() {
    const page = usePage();
    const { isMobile, setOpenMobile } = useSidebar();
    const groups = page.props.nav.groups as NavGroup[];
    const homeUrl = typeof page.props.homeUrl === 'string' ? page.props.homeUrl : '/';

    const closeMobileDrawer = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };

    const footerNavItems: NavItem[] = [
        {
            title: 'About TB-AI',
            href: about(),
            icon: Globe,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeUrl} prefetch onClick={closeMobileDrawer}>
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
                <NavMain groups={groups} icons={icons} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

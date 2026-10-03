import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    ClipboardPlus,
    Globe,
    LayoutGrid,
    List,
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        ...(page.props.registerUrl
            ? [
                  {
                      title: "Today's register",
                      href: page.props.registerUrl,
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(page.props.screeningUrl
            ? [
                  {
                      title: 'Screening',
                      href: page.props.screeningUrl,
                      icon: ClipboardPlus,
                  },
              ]
            : []),
        ...(page.props.profileUrl
            ? [
                  {
                      title: 'Facility profile',
                      href: page.props.profileUrl,
                      icon: Building2,
                  },
              ]
            : []),
        ...(page.props.facilitiesUrl
            ? [
                  {
                      title: 'Facilities',
                      href: page.props.facilitiesUrl,
                      icon: List,
                  },
              ]
            : []),
    ];

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
                            <Link href={dashboardUrl} prefetch>
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

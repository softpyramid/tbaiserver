import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FacilityTabBar } from '@/components/facility-tab-bar';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { ReturnSync } from '@/components/return-sync';
import { cn } from '@/lib/utils';
import type { DashboardInvitation } from '@/types';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { nav, i18n } = usePage().props;
    const facility = nav.shell === 'facility';

    return (
        <AppShell variant="sidebar" drawerBelow={facility ? 768 : 1024}>
            <NavShellMarker shell={nav.shell} dir={i18n.dir} />
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={cn(
                    'min-w-0 overflow-x-clip',
                    facility && 'max-md:pb-[var(--entry-tab-offset)]',
                )}
            >
                <div className={facility ? 'max-md:hidden' : undefined}>
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                </div>
                {children}
            </AppContent>
            {facility ? <FacilityTabBar /> : null}
            <ReturnSync />
            <PendingInvitations />
        </AppShell>
    );
}

function PendingInvitations() {
    const invitations = usePage().props.pendingInvitations;
    const list = Array.isArray(invitations)
        ? (invitations as DashboardInvitation[])
        : [];
    const [open, setOpen] = useState(list.length > 0);

    return (
        <PendingInvitationsModal
            invitations={list}
            open={list.length > 0 && open}
            onOpenChange={setOpen}
        />
    );
}

function NavShellMarker({ shell, dir }: { shell: string; dir: 'ltr' | 'rtl' }) {
    useEffect(() => {
        document.documentElement.dataset.navShell = shell;
        document.documentElement.dir = dir;
    }, [dir, shell]);

    return null;
}

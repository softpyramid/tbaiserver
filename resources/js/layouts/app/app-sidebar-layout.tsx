import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { PhoneTabBar } from '@/components/phone-tab-bar';
import { PhoneTopBar } from '@/components/phone-top-bar';
import { ReturnSync } from '@/components/return-sync';
import { useSessionGuard } from '@/hooks/use-session-guard';
import { cn } from '@/lib/utils';
import type { DashboardInvitation } from '@/types';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { nav, i18n } = usePage().props;
    const facility = nav.shell === 'facility';
    const phoneTabs = nav.tabs.length > 0;

    useSessionGuard();

    return (
        <AppShell variant="sidebar" drawerBelow={facility ? 768 : 1024}>
            <NavShellMarker
                shell={nav.shell}
                phoneTabs={phoneTabs}
                dir={i18n.dir}
            />
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={cn(
                    'min-w-0 overflow-x-clip',
                    phoneTabs && 'max-md:pb-[var(--entry-tab-offset)]',
                )}
            >
                {phoneTabs ? <PhoneTopBar /> : null}
                <div className={phoneTabs ? 'max-md:hidden' : undefined}>
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                </div>
                {children}
            </AppContent>
            {phoneTabs ? <PhoneTabBar /> : null}
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

function NavShellMarker({
    shell,
    phoneTabs,
    dir,
}: {
    shell: string;
    phoneTabs: boolean;
    dir: 'ltr' | 'rtl';
}) {
    useEffect(() => {
        document.documentElement.dataset.navShell = shell;
        document.documentElement.dataset.phoneTabs = phoneTabs ? '1' : '0';
        document.documentElement.dir = dir;
    }, [dir, shell, phoneTabs]);

    return null;
}

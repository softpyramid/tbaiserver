import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { AppShell } from '@/components/app-shell';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <ImpersonationBanner />
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent variant="header">{children}</AppContent>
        </AppShell>
    );
}

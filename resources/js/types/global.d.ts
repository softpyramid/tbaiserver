import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            registerUrl: string | null;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            tbai: {
                showValidatedClaims: boolean;
                showSupporterLogos: boolean;
                showPartnerLine: boolean;
                showTelehealth: boolean;
                developmentNotice: string | null;
                partnerLine: string | null;
                contact: {
                    location: string;
                    email: string;
                    reach: string;
                };
            };
            [key: string]: unknown;
        };
    }
}

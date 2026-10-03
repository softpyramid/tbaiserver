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
            profileUrl: string | null;
            myFacilityUrl: string | null;
            screeningUrl: string | null;
            facilitiesUrl: string | null;
            nav: {
                shell: 'facility' | 'sidebar';
                activeTab:
                    | 'register'
                    | 'screening'
                    | 'facility'
                    | 'account'
                    | null;
                groups: {
                    key: string;
                    label: string;
                    items: {
                        key: string;
                        label: string;
                        href: string | null;
                        badge: number | null;
                        expanded: boolean;
                        children: {
                            key: string;
                            label: string;
                            href: string;
                            badge: number | null;
                        }[];
                    }[];
                }[];
            };
            i18n: {
                locale: string;
                dir: 'ltr' | 'rtl';
                entry: Record<string, string>;
            };
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

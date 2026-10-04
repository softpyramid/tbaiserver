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
            impersonation: {
                name: string;
                stopUrl: string;
                banner: string;
                returnLabel: string;
                stopLabel: string;
            } | null;
            nav: {
                shell: 'facility' | 'sidebar';
                activeTab: string | null;
                tabs: {
                    id: string;
                    label: string;
                    href: string;
                    icon: string;
                    current: boolean;
                }[];
                more: {
                    id: string;
                    label: string;
                    href: string;
                    icon: string;
                    current: boolean;
                }[];
                commandTabs: {
                    id: string;
                    label: string;
                    href: string;
                    current: boolean;
                    badge: number | null;
                }[];
                tabsLabel: string;
                moreLabel: string;
                logoutLabel: string;
                groups: {
                    key: string;
                    label: string;
                    items: {
                        key: string;
                        label: string;
                        href: string | null;
                        badge: number | null;
                        expanded: boolean;
                        current: boolean;
                        children: {
                            key: string;
                            label: string;
                            href: string;
                            badge: number | null;
                            current?: boolean;
                            blank?: boolean;
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

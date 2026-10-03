import { usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    ClipboardPlus,
    CircleUser,
} from 'lucide-react';
import { edit as account } from '@/routes/profile';

export type FacilityTab = {
    id: 'register' | 'screening' | 'facility' | 'account';
    href: string;
    labelKey: 'nav_register' | 'nav_screening' | 'nav_facility' | 'nav_account';
    icon: typeof ClipboardList;
};

type NavProps = {
    registerUrl: string | null;
    screeningUrl: string | null;
    profileUrl: string | null;
    myFacilityUrl: string | null;
    facilitiesUrl: string | null;
    currentTeam: { slug: string } | null;
};

/**
 * Phone tabs for facility staff. Add a tab by appending a row here.
 * Command center items come from the server sidebar.
 */
export function facilityTabs(props: NavProps): FacilityTab[] {
    const tabs: FacilityTab[] = [];

    if (props.registerUrl) {
        tabs.push({
            id: 'register',
            href: props.registerUrl,
            labelKey: 'nav_register',
            icon: ClipboardList,
        });
    }

    if (props.screeningUrl) {
        tabs.push({
            id: 'screening',
            href: props.screeningUrl,
            labelKey: 'nav_screening',
            icon: ClipboardPlus,
        });
    }

    const facilityHref = props.myFacilityUrl ?? props.profileUrl;

    if (facilityHref) {
        tabs.push({
            id: 'facility',
            href: facilityHref,
            labelKey: 'nav_facility',
            icon: Building2,
        });
    }

    tabs.push({
        id: 'account',
        href: account.url(),
        labelKey: 'nav_account',
        icon: CircleUser,
    });

    return tabs;
}

export function isTabActive(tab: FacilityTab, currentUrl: string): boolean {
    const path = strip(currentUrl);
    const target = strip(tab.href);

    switch (tab.id) {
        case 'account':
            return path === target || path.startsWith('/settings');
        case 'register':
            return path === target || path.endsWith('/returns/today');
        case 'screening':
            return path === target || path.includes('/screening');
        case 'facility':
            return (
                path === target ||
                path.endsWith('/my-facility') ||
                path.endsWith('/profile') ||
                path.endsWith('/members')
            );
        default: {
            const unreachable: never = tab.id;

            return unreachable;
        }
    }
}

function strip(url: string): string {
    const path = url.split('?')[0] ?? url;

    return path.length > 1 ? path.replace(/\/$/, '') : path;
}

export function useNavProps(): NavProps {
    const page = usePage();

    return {
        registerUrl: page.props.registerUrl,
        screeningUrl: page.props.screeningUrl,
        profileUrl: page.props.profileUrl,
        myFacilityUrl: page.props.myFacilityUrl,
        facilitiesUrl: page.props.facilitiesUrl,
        currentTeam: page.props.currentTeam,
    };
}

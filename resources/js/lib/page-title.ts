export const APP_NAME: string = import.meta.env.VITE_APP_NAME || 'TB-AI';

/** Browser tab title for pages that set none of their own. */
export const SITE_TITLE = 'TB-AI | TB register and decision support';

/**
 * The browser tab title for a page title. The signed-in layout sets
 * SITE_TITLE as a default so the previous page's title (for example
 * "Log in") never survives a visit; each page's own <Head> replaces it.
 */
export function pageTitle(title: string): string {
    return title && title !== SITE_TITLE
        ? `${title} - ${APP_NAME}`
        : SITE_TITLE;
}

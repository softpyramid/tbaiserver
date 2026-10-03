import { useCallback, useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;

const mediaQueries = new Map<number, MediaQueryList>();

function mediaFor(maxWidth: number): MediaQueryList | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    const existing = mediaQueries.get(maxWidth);

    if (existing) {
        return existing;
    }

    const created = window.matchMedia(`(max-width: ${maxWidth}px)`);
    mediaQueries.set(maxWidth, created);

    return created;
}

export function useMaxWidth(maxWidth: number): boolean {
    const subscribe = useCallback(
        (onStoreChange: () => void) => {
            const media = mediaFor(maxWidth);

            if (!media) {
                return () => {};
            }

            media.addEventListener('change', onStoreChange);

            return () => {
                media.removeEventListener('change', onStoreChange);
            };
        },
        [maxWidth],
    );

    const getSnapshot = useCallback(
        () => mediaFor(maxWidth)?.matches ?? false,
        [maxWidth],
    );

    return useSyncExternalStore(subscribe, getSnapshot, () => false);
}

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function isSmallerThanBreakpoint(): boolean {
    return mql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}

import { useEffect, useRef, type RefObject } from 'react';

const FOCUSABLE =
    'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';

/**
 * Keep keyboard focus inside `ref` while it is mounted, close on Escape and
 * give focus back to whatever had it before.
 */
export function useFocusTrap(
    ref: RefObject<HTMLElement | null>,
    onClose: () => void,
) {
    const close = useRef(onClose);

    useEffect(() => {
        close.current = onClose;
    });

    useEffect(() => {
        const node = ref.current;
        const previous = document.activeElement as HTMLElement | null;

        node?.querySelector<HTMLElement>(FOCUSABLE)?.focus();

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                close.current();

                return;
            }

            if (e.key !== 'Tab' || !node) {
                return;
            }

            const items = [
                ...node.querySelectorAll<HTMLElement>(FOCUSABLE),
            ].filter(
                (el) =>
                    el.offsetParent !== null || el === document.activeElement,
            );

            if (items.length === 0) {
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onKey, true);

        return () => {
            document.removeEventListener('keydown', onKey, true);
            previous?.focus?.();
        };
    }, [ref]);
}

import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export function useUnsavedChanges(when: boolean, message: string) {
    const bypass = useRef(false);
    const whenRef = useRef(when);
    const messageRef = useRef(message);

    whenRef.current = when;
    messageRef.current = message;

    useEffect(() => {
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!whenRef.current || bypass.current) {
                return;
            }

            event.preventDefault();
            event.returnValue = messageRef.current;
        };

        window.addEventListener('beforeunload', onBeforeUnload);

        const remove = router.on('before', (event) => {
            const visit = (
                event as CustomEvent<{ visit?: { prefetch?: boolean } }>
            ).detail?.visit;

            if (visit?.prefetch || !whenRef.current || bypass.current) {
                return;
            }

            if (!window.confirm(messageRef.current)) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            remove();
        };
    }, []);

    return {
        allowNextLeave() {
            bypass.current = true;
        },
        resetLeave() {
            bypass.current = false;
        },
    };
}

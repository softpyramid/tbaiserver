import { useEffect } from 'react';
import { syncQueuedReturns } from '@/lib/return-queue';

export function ReturnSync() {
    useEffect(() => {
        const sync = () => {
            void syncQueuedReturns();
        };

        sync();
        window.addEventListener('online', sync);

        return () => window.removeEventListener('online', sync);
    }, []);

    return null;
}

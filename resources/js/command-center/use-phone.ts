import { useEffect, useState } from 'react';

/** True below the phone breakpoint used by the stylesheet. */
export function usePhone(): boolean {
    const [phone, setPhone] = useState(
        () => window.matchMedia('(max-width: 767px)').matches,
    );

    useEffect(() => {
        const query = window.matchMedia('(max-width: 767px)');
        const apply = () => setPhone(query.matches);

        query.addEventListener('change', apply);

        return () => query.removeEventListener('change', apply);
    }, []);

    return phone;
}

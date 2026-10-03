import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FacilityForm, {
    type FacilityFormValues,
} from '@/components/facility-form';
import { useEntryI18n } from '@/lib/i18n';

type Choice = {
    value: string;
    label: string;
};

type CityOption = {
    id: number;
    name: string;
};

type TalukaOption = {
    id: number;
    cityId: number;
    name: string;
};

type Summary = {
    name: string;
    sector: string;
    city: string;
};

type Props = {
    teamSlug: string;
    canEdit: boolean;
    dateLabel: string;
    summary: Summary;
    facility: FacilityFormValues;
    sectors: Choice[];
    kinds: Choice[];
    cities: CityOption[];
    talukas: TalukaOption[];
};

export default function EditFacility(props: Props) {
    const { t } = useEntryI18n();
    const [savedMessage, setSavedMessage] = useState<string | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const message = (event as CustomEvent).detail?.flash?.savedProfile;

            if (typeof message === 'string') {
                setSavedMessage(message);
            }
        });
    }, []);

    return (
        <>
            <Head title={t('profile_title')} />
            <FacilityForm
                titleKey="profile_title"
                savedMessage={savedMessage}
                {...props}
            />
        </>
    );
}

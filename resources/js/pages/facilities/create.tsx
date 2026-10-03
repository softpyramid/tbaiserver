import { Head } from '@inertiajs/react';
import FacilityForm from '@/components/facility-form';
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

type Props = {
    dateLabel: string;
    canEdit: boolean;
    sectors: Choice[];
    kinds: Choice[];
    cities: CityOption[];
    talukas: TalukaOption[];
};

export default function CreateFacility(props: Props) {
    const { t } = useEntryI18n();

    return (
        <>
            <Head title={t('create_title')} />
            <FacilityForm titleKey="create_title" {...props} />
        </>
    );
}

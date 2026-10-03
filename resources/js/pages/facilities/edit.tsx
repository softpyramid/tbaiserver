import { Head } from '@inertiajs/react';
import FacilityForm, {
    type FacilityFormValues,
} from '@/components/facility-form';

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
    teamSlug: string;
    facility: FacilityFormValues;
    sectors: Choice[];
    kinds: Choice[];
    cities: CityOption[];
    talukas: TalukaOption[];
};

export default function EditFacility({
    teamSlug,
    facility,
    ...options
}: Props) {
    return (
        <>
            <Head title="Facility profile" />
            <FacilityForm
                title="Facility profile"
                teamSlug={teamSlug}
                facility={facility}
                {...options}
            />
        </>
    );
}

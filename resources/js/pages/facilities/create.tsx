import { Head } from '@inertiajs/react';
import FacilityForm from '@/components/facility-form';

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
    sectors: Choice[];
    kinds: Choice[];
    cities: CityOption[];
    talukas: TalukaOption[];
};

export default function CreateFacility(props: Props) {
    return (
        <>
            <Head title="Create facility" />
            <FacilityForm
                title="Create facility"
                description="Staff join by invitation."
                {...props}
            />
        </>
    );
}

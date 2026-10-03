import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FacilityForm, {
    type FacilityFormValues,
} from '@/components/facility-form';
import TeamAreaShell from '@/components/team-area-shell';
import { useEntryI18n } from '@/lib/i18n';
import type { TeamAreaHeader } from '@/types/team-area';

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
    area: TeamAreaHeader;
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
            <TeamAreaShell area={props.area}>
                <FacilityForm
                    titleKey="profile_title"
                    savedMessage={savedMessage}
                    teamSlug={props.teamSlug}
                    canEdit={props.canEdit}
                    dateLabel={props.dateLabel}
                    summary={props.summary}
                    facility={props.facility}
                    sectors={props.sectors}
                    kinds={props.kinds}
                    cities={props.cities}
                    talukas={props.talukas}
                />
            </TeamAreaShell>
        </>
    );
}

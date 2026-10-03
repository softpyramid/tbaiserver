import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FacilityForm, {
    type FacilityFormValues,
} from '@/components/facility-form';
import TeamAreaShell from '@/components/team-area-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    canDeactivate: boolean;
    deactivateUrl: string;
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
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);

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
            <TeamAreaShell area={props.area}>
                <FacilityForm
                    titleKey="profile_title"
                    embedded
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
                    belowFields={
                        props.canDeactivate ? (
                            <Button
                                type="button"
                                variant="destructive"
                                data-test="deactivate-facility-button"
                                className="h-12 w-full text-base"
                                onClick={() => setConfirmDeactivate(true)}
                            >
                                {t('deactivate_facility')}
                            </Button>
                        ) : null
                    }
                />
            </TeamAreaShell>
            <Dialog
                open={confirmDeactivate}
                onOpenChange={setConfirmDeactivate}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('deactivate_facility')}</DialogTitle>
                        <DialogDescription>
                            {t('deactivate_facility_body', {
                                name: props.facility.name,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setConfirmDeactivate(false)}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="deactivate-facility-confirm"
                            onClick={() => router.post(props.deactivateUrl)}
                        >
                            {t('deactivate_facility')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

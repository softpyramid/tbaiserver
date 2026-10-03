import { Form } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/facilities';

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

export type FacilityFormValues = {
    name: string;
    sector: string;
    kind: string;
    cityId: number | null;
    talukaId: number | null;
    code: string;
    active: boolean;
};

type Props = {
    title: string;
    description?: string;
    teamSlug?: string;
    facility?: FacilityFormValues;
    sectors: Choice[];
    kinds: Choice[];
    cities: CityOption[];
    talukas: TalukaOption[];
};

const selectClassName =
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm';

export default function FacilityForm({
    title,
    description,
    teamSlug,
    facility,
    sectors,
    kinds,
    cities,
    talukas,
}: Props) {
    const [cityId, setCityId] = useState(
        facility?.cityId === null || facility?.cityId === undefined
            ? ''
            : String(facility.cityId),
    );
    const [talukaId, setTalukaId] = useState(
        facility?.talukaId === null || facility?.talukaId === undefined
            ? ''
            : String(facility.talukaId),
    );

    const talukasForCity = talukas.filter(
        (taluka) => String(taluka.cityId) === cityId,
    );

    return (
        <div className="mx-auto flex w-full max-w-md flex-col gap-8 px-4 py-6">
            <Heading title={title} description={description} />

            <Form
                {...(teamSlug ? update.form(teamSlug) : store.form())}
                className="space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={facility?.name ?? ''}
                                required
                                maxLength={255}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sector">Sector</Label>
                            <select
                                id="sector"
                                name="sector"
                                className={selectClassName}
                                defaultValue={facility?.sector ?? ''}
                                required
                            >
                                <option value="" disabled>
                                    Select a sector
                                </option>
                                {sectors.map((sector) => (
                                    <option
                                        key={sector.value}
                                        value={sector.value}
                                    >
                                        {sector.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.sector} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="kind">Kind</Label>
                            <select
                                id="kind"
                                name="kind"
                                className={selectClassName}
                                defaultValue={facility?.kind ?? ''}
                                required
                            >
                                <option value="" disabled>
                                    Select a kind
                                </option>
                                {kinds.map((kind) => (
                                    <option key={kind.value} value={kind.value}>
                                        {kind.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.kind} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="city_id">City</Label>
                            <select
                                id="city_id"
                                name="city_id"
                                className={selectClassName}
                                value={cityId}
                                required
                                onChange={(event) => {
                                    setCityId(event.target.value);
                                    setTalukaId('');
                                }}
                            >
                                <option value="" disabled>
                                    Select a city
                                </option>
                                {cities.map((city) => (
                                    <option key={city.id} value={city.id}>
                                        {city.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.city_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="taluka_id">Taluka</Label>
                            <select
                                id="taluka_id"
                                name="taluka_id"
                                className={selectClassName}
                                value={talukaId}
                                onChange={(event) =>
                                    setTalukaId(event.target.value)
                                }
                            >
                                <option value="">None</option>
                                {talukasForCity.map((taluka) => (
                                    <option key={taluka.id} value={taluka.id}>
                                        {taluka.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.taluka_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">Programme code</Label>
                            <Input
                                id="code"
                                name="code"
                                defaultValue={facility?.code ?? ''}
                                maxLength={255}
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="flex items-center gap-3">
                            <input
                                id="active"
                                name="active"
                                type="checkbox"
                                value="1"
                                defaultChecked={facility?.active ?? true}
                                className={cn(
                                    'border-input size-4 rounded border',
                                )}
                            />
                            <Label htmlFor="active">Active</Label>
                        </div>
                        <InputError message={errors.active} />

                        <Button type="submit" disabled={processing}>
                            {teamSlug ? 'Save profile' : 'Create facility'}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}

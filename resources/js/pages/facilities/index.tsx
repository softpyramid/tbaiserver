import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/facilities';

type FacilityRow = {
    slug: string;
    name: string;
    sector: string;
    city: string;
    active: boolean;
};

type Props = {
    facilities: FacilityRow[];
};

export default function FacilitiesIndex({ facilities }: Props) {
    return (
        <>
            <Head title="Facilities" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-8 px-4 py-6">
                <Heading title="Facilities" />

                <Button asChild>
                    <Link href={create.url()}>Create facility</Link>
                </Button>

                {facilities.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No facilities yet.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {facilities.map((facility) => (
                            <li
                                key={facility.slug}
                                className="rounded-lg border p-3"
                            >
                                <Link
                                    href={edit.url(facility.slug)}
                                    className="font-medium"
                                >
                                    {facility.name}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {facility.sector}. {facility.city}.{' '}
                                    {facility.active ? 'Active' : 'Inactive'}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

import { useEntryI18n } from '@/lib/i18n';

export default function FacilitySummaryCard({
    name,
    sector,
    city,
}: {
    name: string;
    sector: string;
    city: string;
}) {
    const { t } = useEntryI18n();
    const rows = [
        { label: t('facility_name'), value: name },
        { label: t('sector'), value: sector },
        { label: t('city'), value: city },
    ];

    return (
        <section
            className="rounded-xl border bg-card p-4"
            data-test="facility-summary"
        >
            <dl className="space-y-3 text-base">
                {rows.map((row) => (
                    <div key={row.label}>
                        <dt className="font-medium text-foreground">
                            {row.label}
                        </dt>
                        <dd className="text-foreground">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

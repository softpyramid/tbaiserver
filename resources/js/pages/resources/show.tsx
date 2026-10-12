import { Head } from '@inertiajs/react';
import { type SlidePhoto, SlidePhotoGallery } from '@/components/slide-photos';

type Line = {
    label: string;
    value: string;
};

type HistoryRow = {
    when: string;
    who: string;
    field: string;
    old: string;
    new: string;
};

type Column = {
    key: string;
    label: string;
};

type Props = {
    title: string;
    summary: Line[];
    historyTitle: string;
    historyEmpty: string;
    historyColumns: Column[];
    history: HistoryRow[];
    slidePhotosTitle?: string;
    slidePhotos?: SlidePhoto[] | null;
};

export default function ResourceShow({
    title,
    summary,
    historyTitle,
    historyEmpty,
    historyColumns,
    history,
    slidePhotosTitle,
    slidePhotos,
}: Props) {
    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-3xl min-w-0 flex-col gap-8 px-4 py-6">
                <h1 className="text-xl font-semibold">{title}</h1>

                <dl className="flex flex-col gap-3">
                    {summary.map((line) => (
                        <div key={line.label}>
                            <dt className="text-sm text-muted-foreground">
                                {line.label}
                            </dt>
                            <dd>{line.value}</dd>
                        </div>
                    ))}
                </dl>

                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">{historyTitle}</h2>
                    {history.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {historyEmpty}
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {history.map((row) => (
                                <li
                                    key={`${row.when}-${row.field}-${row.old}-${row.new}`}
                                    className="rounded-lg border p-3"
                                >
                                    {historyColumns.map((column) => (
                                        <div key={column.key}>
                                            <div className="text-sm text-muted-foreground">
                                                {column.label}
                                            </div>
                                            <div>
                                                {
                                                    row[
                                                        column.key as keyof HistoryRow
                                                    ]
                                                }
                                            </div>
                                        </div>
                                    ))}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {slidePhotos && slidePhotosTitle ? (
                    <section
                        className="flex flex-col gap-3"
                        data-test="detail-slide-photos"
                    >
                        <h2 className="text-lg font-semibold">
                            {slidePhotosTitle}
                        </h2>
                        <SlidePhotoGallery photos={slidePhotos} />
                    </section>
                ) : null}
            </div>
        </>
    );
}

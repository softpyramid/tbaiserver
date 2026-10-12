import { router, usePage } from '@inertiajs/react';
import {
    Camera,
    ImageOff,
    ImageUp,
    ShieldAlert,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useEntryI18n } from '@/lib/i18n';

export type SlidePhoto = {
    id: number;
    label: string | null;
    url: string;
    thumbnailUrl: string | null;
    uploadedBy: string;
    uploadedAt: string;
    mimeType: string;
    size: number;
    deleteUrl: string | null;
};

export type SlidePhotosProps = {
    photos: SlidePhoto[];
    uploadUrl: string | null;
    needsSavedReturn: boolean;
    maxFiles: number;
    maxMegabytes: number;
    accept: string;
};

type Selected = {
    key: string;
    file: File;
    label: string;
    preview: string;
};

let nextKey = 0;

/**
 * Slide photo capture and gallery for the BMU daily return. Photos are stored
 * privately and opened through authorized links only. No AI processing.
 */
export default function SlidePhotos({ slides }: { slides: SlidePhotosProps }) {
    const { t } = useEntryI18n();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [selected, setSelected] = useState<Selected[]>([]);
    const [uploading, setUploading] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const cameraRef = useRef<HTMLInputElement>(null);
    const deviceRef = useRef<HTMLInputElement>(null);
    const headingId = useId();
    const selectedRef = useRef(selected);
    selectedRef.current = selected;

    useEffect(() => {
        return () =>
            selectedRef.current.forEach((item) =>
                URL.revokeObjectURL(item.preview),
            );
    }, []);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash as
                | Record<string, unknown>
                | undefined;

            if (flash?.slidePhotosSaved) {
                setNotice(t('slide_photos_saved'));
            } else if (flash?.slidePhotoDeleted) {
                setNotice(t('slide_photos_deleted'));
            }
        });
    }, [t]);

    const slideErrors = Object.entries(errors ?? {}).filter(
        ([key]) =>
            key === 'photos' ||
            key.startsWith('photos.') ||
            key.startsWith('labels.'),
    );

    function addFiles(list: FileList | null) {
        if (!list) {
            return;
        }

        const added = Array.from(list).map((file) => ({
            key: `slide-${nextKey++}`,
            file,
            label: '',
            preview: URL.createObjectURL(file),
        }));

        setSelected((current) =>
            [...current, ...added].slice(0, slides.maxFiles),
        );
        setNotice(null);
    }

    function remove(key: string) {
        setSelected((current) => {
            const item = current.find((entry) => entry.key === key);

            if (item) {
                URL.revokeObjectURL(item.preview);
            }

            return current.filter((entry) => entry.key !== key);
        });
    }

    function upload() {
        if (slides.uploadUrl === null || selected.length === 0) {
            return;
        }

        router.post(
            slides.uploadUrl,
            {
                photos: selected.map((item) => item.file),
                labels: selected.map((item) => item.label),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onSuccess: (page) => {
                    if (
                        Object.keys(
                            (page.props.errors as Record<string, string>) ?? {},
                        ).length === 0
                    ) {
                        selected.forEach((item) =>
                            URL.revokeObjectURL(item.preview),
                        );
                        setSelected([]);
                    }
                },
            },
        );
    }

    function destroy(photo: SlidePhoto) {
        if (
            photo.deleteUrl === null ||
            !window.confirm(t('slide_photos_delete_confirm'))
        ) {
            return;
        }

        router.delete(photo.deleteUrl, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <section
            aria-labelledby={headingId}
            className="flex min-w-0 flex-col gap-3 rounded-lg border p-4"
            data-test="slide-photos"
        >
            <h2 id={headingId} className="text-base font-semibold">
                {t('slide_photos_title')}
            </h2>

            <p
                role="note"
                className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100"
                data-test="slide-photos-privacy"
            >
                <ShieldAlert
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0"
                />
                {t('slide_photos_privacy')}
            </p>

            {slides.uploadUrl !== null ? (
                <div className="flex flex-col gap-3">
                    <p className="text-sm text-muted-foreground">
                        {t('slide_photos_intro')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => cameraRef.current?.click()}
                            disabled={uploading}
                        >
                            <Camera aria-hidden="true" />
                            {t('slide_photos_take')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => deviceRef.current?.click()}
                            disabled={uploading}
                        >
                            <ImageUp aria-hidden="true" />
                            {t('slide_photos_choose')}
                        </Button>
                        <input
                            ref={cameraRef}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            multiple
                            className="sr-only"
                            tabIndex={-1}
                            aria-label={t('slide_photos_take')}
                            data-test="slide-photos-camera"
                            onChange={(event) => {
                                addFiles(event.currentTarget.files);
                                event.currentTarget.value = '';
                            }}
                        />
                        <input
                            ref={deviceRef}
                            type="file"
                            accept={`image/*,${slides.accept}`}
                            multiple
                            className="sr-only"
                            tabIndex={-1}
                            aria-label={t('slide_photos_choose')}
                            data-test="slide-photos-device"
                            onChange={(event) => {
                                addFiles(event.currentTarget.files);
                                event.currentTarget.value = '';
                            }}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {t('slide_photos_limits', {
                            size: slides.maxMegabytes,
                            max: slides.maxFiles,
                        })}
                    </p>

                    {selected.length > 0 ? (
                        <div className="flex flex-col gap-3">
                            <p className="text-sm font-medium">
                                {t('slide_photos_selected', {
                                    count: selected.length,
                                })}
                            </p>
                            <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {selected.map((item, index) => (
                                    <li
                                        key={item.key}
                                        className="flex min-w-0 gap-3 rounded-md border p-2"
                                    >
                                        <Preview src={item.preview} alt="" />
                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            <label className="flex flex-col gap-1 text-sm">
                                                <span>
                                                    {t('slide_photos_label')}
                                                </span>
                                                <input
                                                    value={item.label}
                                                    maxLength={60}
                                                    placeholder={t(
                                                        'slide_photos_label_placeholder',
                                                    )}
                                                    onChange={(event) => {
                                                        const value =
                                                            event.currentTarget
                                                                .value;
                                                        setSelected((current) =>
                                                            current.map(
                                                                (entry) =>
                                                                    entry.key ===
                                                                    item.key
                                                                        ? {
                                                                              ...entry,
                                                                              label: value,
                                                                          }
                                                                        : entry,
                                                            ),
                                                        );
                                                    }}
                                                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3"
                                                />
                                            </label>
                                            {errors?.[`photos.${index}`] ? (
                                                <p className="text-sm text-destructive">
                                                    {errors[`photos.${index}`]}
                                                </p>
                                            ) : null}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="w-fit"
                                                onClick={() => remove(item.key)}
                                                disabled={uploading}
                                            >
                                                <X aria-hidden="true" />
                                                {t(
                                                    'slide_photos_remove_selected',
                                                )}
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <Button
                                type="button"
                                className="w-fit"
                                onClick={upload}
                                disabled={uploading}
                                data-test="slide-photos-upload"
                            >
                                {uploading ? <Spinner /> : null}
                                {uploading
                                    ? t('slide_photos_uploading')
                                    : t('slide_photos_upload')}
                            </Button>
                        </div>
                    ) : null}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {slides.needsSavedReturn
                        ? t('slide_photos_save_first')
                        : t('slide_photos_read_only')}
                </p>
            )}

            {slideErrors.length > 0 ? (
                <ul role="alert" className="text-sm text-destructive">
                    {[
                        ...new Set(slideErrors.map(([, message]) => message)),
                    ].map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            ) : null}

            {notice ? (
                <p role="status" className="text-sm text-muted-foreground">
                    {notice}
                </p>
            ) : null}

            <SlidePhotoGallery photos={slides.photos} onDelete={destroy} />
        </section>
    );
}

export function SlidePhotoGallery({
    photos,
    onDelete,
}: {
    photos: SlidePhoto[];
    onDelete?: (photo: SlidePhoto) => void;
}) {
    const { t } = useEntryI18n();

    if (photos.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('slide_photos_empty')}
            </p>
        );
    }

    return (
        <ul
            className="grid grid-cols-2 gap-3 sm:grid-cols-3"
            data-test="slide-photo-gallery"
        >
            {photos.map((photo) => (
                <li
                    key={photo.id}
                    className="flex min-w-0 flex-col gap-1 rounded-md border p-2"
                >
                    <a
                        href={photo.url}
                        target="_blank"
                        rel="noopener"
                        className="block"
                        aria-label={`${t('slide_photos_open')}${photo.label ? `: ${photo.label}` : ''}`}
                    >
                        {photo.thumbnailUrl ? (
                            <img
                                src={photo.thumbnailUrl}
                                alt={photo.label ?? t('slide_photos_title')}
                                loading="lazy"
                                className="aspect-[4/3] w-full rounded object-cover"
                            />
                        ) : (
                            <span className="flex aspect-[4/3] w-full flex-col items-center justify-center gap-1 rounded bg-muted p-2 text-center text-xs text-muted-foreground">
                                <ImageOff
                                    aria-hidden="true"
                                    className="size-5"
                                />
                                {t('slide_photos_no_preview')}
                            </span>
                        )}
                    </a>
                    {photo.label ? (
                        <p className="truncate text-sm font-medium">
                            {photo.label}
                        </p>
                    ) : null}
                    <p className="text-xs text-muted-foreground">
                        {t('slide_photos_uploaded_by', {
                            name: photo.uploadedBy,
                            time: photo.uploadedAt,
                        })}
                    </p>
                    {onDelete && photo.deleteUrl ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-fit text-destructive"
                            onClick={() => onDelete(photo)}
                        >
                            <Trash2 aria-hidden="true" />
                            {t('slide_photos_delete')}
                        </Button>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}

function Preview({ src, alt }: { src: string; alt: string }) {
    const { t } = useEntryI18n();
    const [failed, setFailed] = useState(false);

    if (failed) {
        return (
            <span className="flex size-20 shrink-0 flex-col items-center justify-center rounded bg-muted p-1 text-center text-[10px] text-muted-foreground">
                <ImageOff aria-hidden="true" className="size-4" />
                {t('slide_photos_no_preview')}
            </span>
        );
    }

    return (
        <img
            src={src}
            alt={alt}
            onError={() => setFailed(true)}
            className="size-20 shrink-0 rounded object-cover"
        />
    );
}

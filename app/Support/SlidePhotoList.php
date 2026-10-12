<?php

namespace App\Support;

use App\Models\DailyReturn;
use App\Models\SlidePhoto;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Slide photo props for the daily return pages.
 */
class SlidePhotoList
{
    /**
     * @return list<array{id: int, label: string|null, url: string, thumbnailUrl: string|null, uploadedBy: string, uploadedAt: string, mimeType: string, size: int, deleteUrl: string|null}>
     */
    public function photos(?DailyReturn $dailyReturn, User $user, ?Team $team): array
    {
        if ($dailyReturn === null) {
            return [];
        }

        return array_values($dailyReturn->slidePhotos()
            ->with(['uploader', 'facility.team'])
            ->orderBy('id')
            ->get()
            ->filter(fn (SlidePhoto $photo): bool => Gate::forUser($user)->allows('view', $photo))
            ->map(fn (SlidePhoto $photo): array => [
                'id' => $photo->id,
                'label' => $photo->label,
                'url' => route('slide-photos.show', $photo),
                'thumbnailUrl' => $photo->thumbnail_path === null ? null : route('slide-photos.thumbnail', $photo),
                'uploadedBy' => $photo->uploader->name,
                'uploadedAt' => $photo->created_at === null ? '' : EntryDate::dateTime($photo->created_at),
                'mimeType' => $photo->mime_type,
                'size' => $photo->size,
                'deleteUrl' => $team !== null && Gate::forUser($user)->allows('delete', $photo)
                    ? route('returns.slides.destroy', ['current_team' => $team->slug, 'slidePhoto' => $photo->id])
                    : null,
            ])
            ->all());
    }

    /**
     * @return array{maxFiles: int, maxMegabytes: int, accept: string}
     */
    public function limits(): array
    {
        return [
            'maxFiles' => (int) config('tbai.slide_photos.max_per_upload', 10),
            'maxMegabytes' => (int) round((int) config('tbai.slide_photos.max_kilobytes', 10240) / 1024),
            'accept' => 'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif',
        ];
    }
}

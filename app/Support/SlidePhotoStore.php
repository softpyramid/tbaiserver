<?php

namespace App\Support;

use App\Models\DailyReturn;
use App\Models\SlidePhoto;
use App\Models\User;
use Carbon\CarbonImmutable;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saves slide photos to the private disk and makes a small JPEG thumbnail
 * when the server can decode the format (JPEG, PNG, WebP with GD). HEIC is
 * stored as is and shown without a thumbnail. No AI processing happens here.
 */
class SlidePhotoStore
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    public function store(DailyReturn $dailyReturn, User $user, UploadedFile $file, ?string $label): SlidePhoto
    {
        $diskName = (string) config('tbai.slide_photos.disk', 'slides');
        $disk = Storage::disk($diskName);
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? (string) ($file->guessExtension() ?? 'bin');
        // Random names only. The original file name is never kept.
        $directory = 'facility-'.$dailyReturn->facility_id.'/'.CarbonImmutable::parse($dailyReturn->reported_on)->format('Y/m');
        $name = Str::uuid()->toString();
        $path = $directory.'/'.$name.'.'.$extension;

        $disk->putFileAs($directory, $file, $name.'.'.$extension);

        [$width, $height] = $this->dimensions($file);
        $thumbnail = $this->thumbnail($file, $mime);
        $thumbnailPath = null;

        if ($thumbnail !== null) {
            $thumbnailPath = $directory.'/'.$name.'-thumb.jpg';
            $disk->put($thumbnailPath, $thumbnail);
        }

        $label = $label === null ? null : trim($label);

        return SlidePhoto::query()->create([
            'daily_return_id' => $dailyReturn->id,
            'facility_id' => $dailyReturn->facility_id,
            'uploaded_by' => $user->id,
            'label' => $label === '' ? null : $label,
            'disk' => $diskName,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }

    private function thumbnail(UploadedFile $file, string $mime): ?string
    {
        if (! extension_loaded('gd') || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        try {
            $source = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
                'image/png' => @imagecreatefrompng($file->getRealPath()),
                default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            };

            if (! $source instanceof GdImage) {
                return null;
            }

            $source = $this->orient($source, $file, $mime);
            $width = imagesx($source);
            $height = imagesy($source);
            $targetWidth = max(1, min($width, (int) config('tbai.slide_photos.thumbnail_width', 480)));
            $targetHeight = max(1, (int) round($height * $targetWidth / max(1, $width)));
            $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($thumb, 0, 0, (int) imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($thumb, null, 80);
            $bytes = (string) ob_get_clean();

            return $bytes === '' ? null : $bytes;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Phone cameras store rotation in EXIF. Apply it so thumbnails are upright.
     */
    private function orient(GdImage $image, UploadedFile $file, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}

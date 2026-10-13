<?php

namespace App\Support\Diagnosis;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Puts the slide image for a diagnosis on the private disk, under
 * diagnoses/{facility}/{uuid}.{ext}, with a small JPEG thumbnail beside it.
 * A bundled sample is copied in, so the record does not depend on public/.
 * Sample keys come from config only; a path is never taken from a request.
 */
class DiagnosisImageStore
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return list<string>
     */
    public static function sampleKeys(): array
    {
        return array_keys((array) config('tbai.diagnosis.samples', []));
    }

    public static function samplePublicPath(string $key): string
    {
        $relative = config('tbai.diagnosis.samples.'.$key);

        if (! is_string($relative) || ! in_array($key, self::sampleKeys(), true)) {
            throw new InvalidArgumentException('Unknown sample.');
        }

        return public_path($relative);
    }

    public static function disk(): string
    {
        return (string) config('tbai.diagnosis.disk', 'slides');
    }

    /**
     * @return array{path: string, thumbnail_path: string|null, mime_type: string}
     */
    public function storeUpload(int $facilityId, string $uuid, UploadedFile $file): array
    {
        return $this->put($facilityId, $uuid, (string) $file->getRealPath(), (string) $file->getMimeType());
    }

    /**
     * @return array{path: string, thumbnail_path: string|null, mime_type: string}
     */
    public function storeSample(int $facilityId, string $uuid, string $key): array
    {
        $source = self::samplePublicPath($key);
        $mime = (string) (getimagesize($source)['mime'] ?? 'image/jpeg');

        return $this->put($facilityId, $uuid, $source, $mime);
    }

    /**
     * @return array{path: string, thumbnail_path: string|null, mime_type: string}
     */
    private function put(int $facilityId, string $uuid, string $source, string $mime): array
    {
        $disk = Storage::disk(self::disk());
        $directory = 'diagnoses/'.$facilityId;
        $path = $directory.'/'.$uuid.'.'.(self::EXTENSIONS[$mime] ?? 'jpg');

        $disk->put($path, (string) file_get_contents($source));

        $thumbnail = $this->thumbnail($source, $mime);
        $thumbnailPath = null;

        if ($thumbnail !== null) {
            $thumbnailPath = $directory.'/'.$uuid.'-thumb.jpg';
            $disk->put($thumbnailPath, $thumbnail);
        }

        return ['path' => $path, 'thumbnail_path' => $thumbnailPath, 'mime_type' => $mime];
    }

    private function thumbnail(string $source, string $mime): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $image = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($source),
                'image/png' => @imagecreatefrompng($source),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                default => false,
            };

            if (! $image instanceof GdImage) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $targetWidth = max(1, min($width, (int) config('tbai.slide_photos.thumbnail_width', 480)));
            $targetHeight = max(1, (int) round($height * $targetWidth / max(1, $width)));
            $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($thumb, 0, 0, (int) imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($thumb, null, 80);
            $bytes = (string) ob_get_clean();

            return $bytes === '' ? null : $bytes;
        } catch (Throwable) {
            return null;
        }
    }
}

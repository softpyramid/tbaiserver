<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A microscopy slide photo attached to a BMU daily return.
 *
 * @property int $id
 * @property int $daily_return_id
 * @property int $facility_id
 * @property int $uploaded_by
 * @property string|null $label
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $created_at
 */
#[Fillable([
    'daily_return_id',
    'facility_id',
    'uploaded_by',
    'label',
    'disk',
    'path',
    'thumbnail_path',
    'mime_type',
    'size',
    'width',
    'height',
])]
class SlidePhoto extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Remove the files with the row so nothing is left on disk.
        static::deleted(function (SlidePhoto $photo): void {
            $disk = Storage::disk($photo->disk);
            $disk->delete(array_values(array_filter([$photo->path, $photo->thumbnail_path])));
        });
    }

    /**
     * @return BelongsTo<DailyReturn, $this>
     */
    public function dailyReturn(): BelongsTo
    {
        return $this->belongsTo(DailyReturn::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A simulated AI diagnosis demo on a patient (features.ai_demo). The result
 * comes from the bound DiagnosisEngine; while simulated is true it is not an
 * AI or clinical result. Symptoms and notes are encrypted; never log them.
 *
 * @property int $id
 * @property string $uuid
 * @property int $patient_id
 * @property int $facility_id
 * @property string $test_type
 * @property string $image_source
 * @property string $image_disk
 * @property string $image_path
 * @property string|null $thumbnail_path
 * @property string $mime_type
 * @property string|null $symptoms
 * @property string|null $notes
 * @property string $result
 * @property string|null $grade
 * @property list<array{x: float, y: float, w: float, h: float, label: string, confidence: float}> $findings
 * @property string $engine
 * @property string $engine_version
 * @property bool $simulated
 * @property int $created_by
 * @property Carbon $analysed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient $patient
 * @property-read Facility $facility
 * @property-read User $creator
 */
#[Fillable([
    'uuid', 'patient_id', 'facility_id', 'test_type', 'image_source', 'image_disk', 'image_path',
    'thumbnail_path', 'mime_type', 'symptoms', 'notes', 'result', 'grade', 'findings', 'engine',
    'engine_version', 'simulated', 'created_by', 'analysed_at',
])]
#[Hidden(['symptoms', 'notes'])]
class Diagnosis extends Model
{
    public const TEST_TB_ZN_SMEAR = 'tb_zn_smear';

    public const SOURCES = ['camera', 'upload', 'sample'];

    protected $table = 'diagnoses';

    protected static function booted(): void
    {
        static::creating(function (Diagnosis $diagnosis): void {
            if (! is_string($diagnosis->getAttribute('uuid')) || $diagnosis->getAttribute('uuid') === '') {
                $diagnosis->uuid = (string) Str::uuid();
            }
        });

        // Remove the files with the row so nothing is left on disk.
        static::deleted(function (Diagnosis $diagnosis): void {
            Storage::disk($diagnosis->image_disk)
                ->delete(array_values(array_filter([$diagnosis->image_path, $diagnosis->thumbnail_path])));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'symptoms' => 'encrypted',
            'notes' => 'encrypted',
            'findings' => 'array',
            'simulated' => 'boolean',
            'analysed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

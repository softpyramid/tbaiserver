<?php

namespace App\Http\Requests;

use App\Models\DailyReturn;
use App\Models\Team;
use App\Support\ReturnDatePolicy;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreSlidePhotosRequest extends FormRequest
{
    /**
     * Facility staff who may edit this return's date may add slide photos to it.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $dailyReturn = $this->route('dailyReturn');
        $user = $this->user();

        if (! $team instanceof Team || ! $dailyReturn instanceof DailyReturn || $user === null) {
            return false;
        }

        $facility = $team->facility;

        if ($facility === null || $dailyReturn->facility_id !== $facility->id || ! $user->worksReturnsAt($team)) {
            return false;
        }

        $date = CarbonImmutable::parse($dailyReturn->reported_on, (string) config('app.timezone'));

        return app(ReturnDatePolicy::class)->canEdit($user, $team, $date);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('tbai.slide_photos.max_kilobytes', 10240);
        $maxFiles = (int) config('tbai.slide_photos.max_per_upload', 10);

        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            // mimes checks the detected content type, not only the extension.
            'photos.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,heic,heif',
                'max:'.$maxKilobytes,
                // JPEG, PNG and WebP must decode as a real image. HEIC is
                // accepted on its detected type alone.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $heic = in_array(strtolower($value->getClientOriginalExtension()), ['heic', 'heif'], true)
                        || in_array($value->getMimeType(), ['image/heic', 'image/heif'], true);

                    if (! $heic && @getimagesize($value->getRealPath()) === false) {
                        $fail(__('entry.slide_photo_type'));
                    }
                },
            ],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => __('entry.slide_photos_required'),
            'photos.max' => __('entry.slide_photos_too_many', ['max' => (int) config('tbai.slide_photos.max_per_upload', 10)]),
            'photos.*.mimes' => __('entry.slide_photo_type'),
            'photos.*.max' => __('entry.slide_photo_size', ['size' => (int) round((int) config('tbai.slide_photos.max_kilobytes', 10240) / 1024)]),
            'photos.*.file' => __('entry.slide_photo_type'),
            'labels.*.max' => __('entry.slide_photo_label_length'),
        ];
    }
}

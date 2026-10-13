<?php

namespace App\Http\Requests;

use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Team;
use App\Models\User;
use App\Support\Diagnosis\DiagnosisImageStore;
use App\Support\Patients\PatientAccess;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Start a simulated diagnosis: a camera photo or upload (JPEG, PNG or WebP
 * that decodes as a real image), or a bundled sample picked by key.
 */
class StoreDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $patient = $this->route('patient');
        $user = $this->user();

        abort_unless($team instanceof Team && $user instanceof User && $patient instanceof Patient, 404);

        $facility = app(PatientAccess::class)->facility($user, $team);
        abort_unless($patient->facility_id === $facility->id, 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['symptoms', 'notes'] as $field) {
            $value = $this->input($field);

            $this->merge([$field => is_string($value) && trim($value) !== '' ? trim($value) : null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_type' => ['required', Rule::in([Diagnosis::TEST_TB_ZN_SMEAR])],
            'image_source' => ['required', Rule::in(Diagnosis::SOURCES)],
            'sample' => ['exclude_unless:image_source,sample', 'required', 'string', Rule::in(DiagnosisImageStore::sampleKeys())],
            // mimes checks the detected content type, not only the extension.
            'image' => [
                'exclude_if:image_source,sample',
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('tbai.diagnosis.max_kilobytes', 10240),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value instanceof UploadedFile && @getimagesize($value->getRealPath()) === false) {
                        $fail(__('entry.diagnosis_image_type'));
                    }
                },
            ],
            'symptoms' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sample.required' => __('entry.diagnosis_sample_required'),
            'sample.in' => __('entry.diagnosis_sample_required'),
            'image.required' => __('entry.diagnosis_image_required'),
            'image.file' => __('entry.diagnosis_image_type'),
            'image.mimes' => __('entry.diagnosis_image_type'),
            'image.max' => __('entry.diagnosis_image_size', ['size' => (int) round((int) config('tbai.diagnosis.max_kilobytes', 10240) / 1024)]),
        ];
    }
}

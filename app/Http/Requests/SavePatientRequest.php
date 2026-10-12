<?php

namespace App\Http\Requests;

use App\Enums\PatientGender;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Team;
use App\Models\User;
use App\Support\Patients\PatientAccess;
use App\Support\Patients\PatientIdentifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a patient. CNIC is required from age 18 and optional for
 * a child; phone is optional. Both are normalized after validation.
 */
class SavePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $user = $this->user();

        abort_unless($team instanceof Team && $user instanceof User && app(PatientAccess::class)->canManage($user, $team), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'register_number' => is_string($this->input('register_number')) ? trim($this->input('register_number')) : $this->input('register_number'),
            'cnic' => is_string($this->input('cnic')) && trim($this->input('cnic')) !== '' ? trim($this->input('cnic')) : null,
            'phone' => is_string($this->input('phone')) && trim($this->input('phone')) !== '' ? trim($this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $patient = $this->route('patient');

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'cnic' => [
                Rule::requiredIf(fn (): bool => is_numeric($this->input('age')) && (int) $this->input('age') >= 18),
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! PatientIdentifiers::isCnic($value)) {
                        $fail(__('entry.patients_cnic_format'));
                    }
                },
            ],
            'phone' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! PatientIdentifiers::isPhone($value)) {
                        $fail(__('entry.patients_phone_format'));
                    }
                },
            ],
            'gender' => ['required', 'string', Rule::enum(PatientGender::class)],
            'age' => ['required', 'integer', 'between:0,120'],
            'register_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('patients', 'register_number')
                    ->where('facility_id', $this->facility()->id)
                    ->ignore($patient instanceof Patient ? $patient->id : null),
            ],
        ];

        if (! $patient instanceof Patient) {
            $rules['informed_of_data_use'] = ['accepted'];
            $rules['screening'] = ['nullable', 'uuid'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.required' => __('entry.patients_cnic_required'),
            'register_number.unique' => __('entry.patients_register_taken'),
            'informed_of_data_use.accepted' => __('entry.patients_informed_required'),
            'age.between' => __('entry.patients_age_range'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('entry.patients_name'),
            'cnic' => __('entry.patients_cnic'),
            'phone' => __('entry.patients_phone'),
            'gender' => __('entry.patients_gender'),
            'age' => __('entry.patients_age'),
            'register_number' => __('entry.patients_register_number'),
        ];
    }

    /**
     * Validated attributes ready to save: CNIC as 13 digits, phone as +923.
     *
     * @return array{name: string, cnic: string|null, phone: string|null, gender: string, age: int, register_number: string}
     */
    public function patientAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'cnic' => PatientIdentifiers::cnicDigits($this->validated('cnic')),
            'phone' => PatientIdentifiers::normalizePhone($this->validated('phone')),
            'gender' => (string) $this->validated('gender'),
            'age' => (int) $this->validated('age'),
            'register_number' => (string) $this->validated('register_number'),
        ];
    }

    public function facility(): Facility
    {
        $team = $this->route('current_team');
        $facility = $team instanceof Team ? $team->facility : null;
        abort_unless($facility instanceof Facility, 404);

        return $facility;
    }
}

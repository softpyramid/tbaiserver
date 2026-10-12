<?php

namespace App\Http\Requests;

use App\Models\SuspectAssessment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSuspectAssessmentRequest extends FormRequest
{
    /**
     * Who may start is checked in the controller (facility staff only).
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility' => ['required', 'string', 'max:255'],
            'age_band' => ['required', 'string', Rule::in(SuspectAssessment::AGE_BANDS)],
            'sex' => ['required', 'string', Rule::in(SuspectAssessment::SEXES)],
        ];
    }
}

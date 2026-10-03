<?php

namespace App\Http\Requests;

use App\Enums\AlertComparator;
use App\Enums\AlertMetric;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isTbaiAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', 'string', Rule::in(array_column(AlertMetric::cases(), 'value'))],
            'comparator' => ['required', 'string', Rule::in(array_column(AlertComparator::cases(), 'value'))],
            'threshold' => ['required', 'numeric'],
            'severity' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:160'],
        ];
    }
}

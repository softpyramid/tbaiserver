<?php

namespace App\Http\Requests;

use App\Models\IncidenceTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidenceTargetRequest extends FormRequest
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
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'year' => ['required', 'integer', Rule::in(IncidenceTarget::years())],
            'value' => ['required', 'numeric', 'gte:0'],
            'source' => ['nullable', 'string', 'max:255'],
            'month' => ['prohibited'],
        ];
    }
}

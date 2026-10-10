<?php

namespace App\Http\Requests;

use App\Enums\AreaType;
use App\Models\City;
use App\Models\Province;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidenceEstimateRequest extends FormRequest
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
            'area_type' => ['required', Rule::enum(AreaType::class)],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'between:2000,2050'],
            'rate_per_100k' => ['required', 'numeric', 'gte:0'],
            'lower_bound' => ['nullable', 'numeric', 'gte:0'],
            'upper_bound' => ['nullable', 'numeric', 'gte:0'],
            'estimated_cases' => ['nullable', 'integer', 'gte:0'],
            'source' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', 'max:255'],
            'reference_url' => ['nullable', 'url', 'max:1024'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->enum('area_type', AreaType::class);

            if ($type === null) {
                return;
            }

            $areaId = $this->input('area_id');

            if ($type === AreaType::Country) {
                if ($areaId !== null && $areaId !== '') {
                    $validator->errors()->add('area_id', __('entry.estimate_country_area_id'));
                }

                return;
            }

            if ($areaId === null || $areaId === '') {
                $validator->errors()->add('area_id', __('entry.estimate_area_required'));

                return;
            }

            $areaId = (int) $areaId;
            $exists = $type === AreaType::District
                ? City::query()->whereKey($areaId)->exists()
                : Province::query()->whereKey($areaId)->exists();

            if (! $exists) {
                $validator->errors()->add('area_id', __('entry.estimate_area_unknown'));
            }
        });
    }
}

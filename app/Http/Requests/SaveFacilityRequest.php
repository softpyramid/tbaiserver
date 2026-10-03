<?php

namespace App\Http\Requests;

use App\Enums\FacilityKind;
use App\Enums\Sector;
use App\Models\Facility;
use App\Models\Taluka;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $team = $this->route('current_team');

        if ($team instanceof Team) {
            $facility = $team->facility;

            return $facility instanceof Facility && $user->can('update', $facility);
        }

        return $user->can('create', Facility::class);
    }

    protected function prepareForValidation(): void
    {
        $talukaId = $this->input('taluka_id');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code') === '' ? null : $this->input('code'),
            'taluka_id' => $talukaId === '' || $talukaId === null ? null : $talukaId,
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sector' => ['required', 'string', Rule::enum(Sector::class)],
            'kind' => ['required', 'string', Rule::enum(FacilityKind::class)],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'taluka_id' => ['nullable', 'integer', 'exists:talukas,id'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('facilities', 'code')->ignore($this->existingFacilityId()),
            ],
            'active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $talukaId = $this->input('taluka_id');

            if ($talukaId === null) {
                return;
            }

            $belongs = Taluka::query()
                ->whereKey($talukaId)
                ->where('city_id', $this->integer('city_id'))
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('taluka_id', 'Choose a taluka in the selected city.');
            }
        });
    }

    private function existingFacilityId(): ?int
    {
        $team = $this->route('current_team');

        if (! $team instanceof Team) {
            return null;
        }

        return $team->facility?->id;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Support\ReturnDatePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveDailyReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $user = $this->user();

        return $team instanceof Team
            && $user !== null
            && $user->worksReturnsAt($team)
            && $team->facility !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reported_on' => ['required', 'date'],
            'suspects' => ['required', 'integer', 'min:0'],
            'tested' => ['required', 'integer', 'min:0'],
            'positive' => ['required', 'integer', 'min:0'],
            'negative' => ['required', 'integer', 'min:0'],
            'started_treatment' => ['required', 'integer', 'min:0'],
            'follow_up' => ['required', 'integer', 'min:0'],
            'median_tat_days' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $positive = (int) $this->input('positive');
            $negative = (int) $this->input('negative');
            $tested = (int) $this->input('tested');

            if ($positive + $negative !== $tested) {
                $validator->errors()->add('tested', __('entry.tested_mismatch', [
                    'sum' => $positive + $negative,
                    'tested' => $tested,
                ]));
            }

            $team = $this->route('current_team');
            $user = $this->user();

            if (! $team instanceof Team || $user === null) {
                return;
            }

            $date = CarbonImmutable::parse($this->string('reported_on')->toString(), (string) config('app.timezone'));

            if (! app(ReturnDatePolicy::class)->canEdit($user, $team, $date)) {
                $validator->errors()->add('reported_on', __('entry.date_closed'));
            }
        });
    }
}

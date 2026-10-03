<?php

namespace App\Http\Requests;

use App\Models\Facility;
use App\Models\Team;
use App\Support\ScreeningScore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScreeningRequest extends FormRequest
{
    /**
     * Fields that must never be stored on a screening session.
     *
     * @var list<string>
     */
    public const PERSONAL = ['name', 'phone', 'national_id', 'nationalId', 'cnic'];

    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $user = $this->user();

        return $team instanceof Team
            && $user !== null
            && $team->facility instanceof Facility
            && $user->can('screen', $team->facility);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $personal = [];

        foreach (self::PERSONAL as $field) {
            $personal[$field] = ['prohibited'];
        }

        return [
            ...$personal,
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'in:yes,no'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Do not record a name, phone, or national ID.';
        $messages = [];

        foreach (self::PERSONAL as $field) {
            $messages[$field.'.prohibited'] = $message;
        }

        return $messages;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $questions = ScreeningScore::questions();

            if ($questions === []) {
                $validator->errors()->add('answers', ScreeningScore::NOT_READY);

                return;
            }

            $answers = $this->input('answers');

            if (! is_array($answers)) {
                return;
            }

            $expected = array_column($questions, 'key');

            foreach (self::PERSONAL as $field) {
                if (array_key_exists($field, $answers)) {
                    $validator->errors()->add(
                        'answers.'.$field,
                        'Do not record a name, phone, or national ID.',
                    );
                }
            }

            foreach ($expected as $key) {
                if (! array_key_exists($key, $answers)) {
                    $validator->errors()->add('answers.'.$key, 'Choose yes or no.');
                }
            }

            foreach (array_keys($answers) as $key) {
                if (! is_string($key)) {
                    continue;
                }

                if (! in_array($key, $expected, true) && ! in_array($key, self::PERSONAL, true)) {
                    $validator->errors()->add('answers.'.$key, 'That question is not on this form.');
                }
            }
        });
    }
}

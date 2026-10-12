<?php

namespace App\Http\Requests;

use App\Models\SuspectAssessment;
use App\Models\SuspectQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SuspectAnswersRequest extends FormRequest
{
    /**
     * Who may answer is checked in the controller (staff of the facility).
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
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string'],
        ];
    }

    /**
     * Every key must be a question of the assessment's rule set (not a
     * profile question) and every value one of its options.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assessment = $this->route('assessment');
            $answers = $this->input('answers');

            if (! $assessment instanceof SuspectAssessment || ! is_array($answers)) {
                return;
            }

            $questions = $assessment->ruleSet->questions
                ->reject(fn (SuspectQuestion $question): bool => $question->isProfile())
                ->keyBy('key');

            foreach ($answers as $key => $value) {
                $question = $questions->get((string) $key);

                if (! $question instanceof SuspectQuestion) {
                    $validator->errors()->add('answers.'.$key, __('entry.suspect_unknown_question'));

                    continue;
                }

                if (! is_string($value) || ! in_array($value, $question->options, true)) {
                    $validator->errors()->add('answers.'.$key, __('entry.suspect_invalid_option'));
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function answers(): array
    {
        $answers = [];

        foreach ((array) $this->input('answers') as $key => $value) {
            if (is_string($value)) {
                $answers[(string) $key] = $value;
            }
        }

        return $answers;
    }
}

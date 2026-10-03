<?php

namespace App\Http\Requests;

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\ProgrammeAssignment;
use App\Support\ProgrammeAssignmentValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgrammeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTbaiAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', Rule::enum(ProgrammeRole::class)],
            'scope_type' => ['nullable', 'string', Rule::enum(ScopeType::class)],
            'scope_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $role = ProgrammeRole::from($this->string('role')->toString());
            $scopeType = $this->input('scope_type');
            $scopeType = is_string($scopeType) && $scopeType !== '' ? $scopeType : null;

            $errors = app(ProgrammeAssignmentValidator::class)->errors(
                $role,
                $scopeType,
                $this->input('scope_id'),
            );

            foreach ($errors as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            if ($errors !== []) {
                return;
            }

            $exists = ProgrammeAssignment::query()
                ->where('user_id', $this->integer('user_id'))
                ->where('role', $role)
                ->when(
                    $scopeType === null,
                    fn ($query) => $query->whereNull('scope_type')->whereNull('scope_id'),
                    fn ($query) => $query->where('scope_type', $scopeType)->where('scope_id', $this->integer('scope_id')),
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('role', 'That assignment already exists.');
            }
        });
    }
}

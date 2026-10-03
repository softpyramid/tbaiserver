<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
            'confirm_email' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => $this->matchesAccountEmail($value)
                    ? null
                    : $fail(__('Type your account email address exactly to confirm.')),
            ],
        ];
    }

    private function matchesAccountEmail(mixed $value): bool
    {
        $email = $this->user()?->email;

        return is_string($value)
            && is_string($email)
            && strtolower(trim($value)) === strtolower($email);
    }
}

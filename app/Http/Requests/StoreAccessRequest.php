<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessRequest extends FormRequest
{
    /**
     * Honeypot field. The React form must use this same name.
     * A filled value is discarded and is not stored.
     */
    public const string HONEYPOT = 'website';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'organisation' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'consent' => ['accepted'],
        ];
    }
}

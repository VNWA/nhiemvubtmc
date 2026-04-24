<?php

namespace App\Http\Requests\Client;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Trim whitespace on incoming text fields, mirroring the
     * normalization the registration / admin flows already perform.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'username' => is_string($this->input('username')) ? trim((string) $this->input('username')) : $this->input('username'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->getKey();

        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($userId),
            'password' => ['nullable', 'confirmed', Password::min(1)],
        ];
    }
}

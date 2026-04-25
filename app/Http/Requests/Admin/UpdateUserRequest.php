<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()->can('update', $user);
    }

    /**
     * Normalize whitespace on optional bank fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_name' => is_string($this->input('bank_name')) ? trim((string) $this->input('bank_name')) : $this->input('bank_name'),
            'bank_account_number' => is_string($this->input('bank_account_number'))
                ? preg_replace('/\s+/', '', (string) $this->input('bank_account_number'))
                : $this->input('bank_account_number'),
            'bank_account_name' => is_string($this->input('bank_account_name'))
                ? mb_strtoupper(trim((string) $this->input('bank_account_name')))
                : $this->input('bank_account_name'),
            'phone' => is_string($this->input('phone'))
                ? trim((string) $this->input('phone'))
                : $this->input('phone'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $editing */
        $editing = $this->route('user');

        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($editing->id),
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:1', 'confirmed'],
            'role' => ['required', 'in:admin,staff,user'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]*$/'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.unique' => __('This username is already taken.'),
            'username.alpha_dash' => __('The username may only contain letters, numbers, dashes, and underscores.'),
            'bank_account_number.regex' => 'Số tài khoản chỉ được chứa chữ số.',
        ];
    }
}

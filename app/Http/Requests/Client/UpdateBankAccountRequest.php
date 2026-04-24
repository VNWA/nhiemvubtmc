<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Trim whitespace and normalize bank account number to digits only.
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
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:120'],
            'bank_account_number' => ['required', 'string', 'min:6', 'max:32', 'regex:/^[0-9]+$/'],
            'bank_account_name' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_account_number.regex' => 'Số tài khoản chỉ được chứa chữ số.',
        ];
    }
}

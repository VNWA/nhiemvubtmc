<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Users can only edit their phone number from the client profile page;
     * name & username are locked and any incoming values are stripped so
     * request spoofing cannot bypass the UI restriction.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone)) {
            $phone = preg_replace('/[\s\-()]+/', '', trim($phone));
            $phone = $phone === '' ? null : $phone;
        }

        $this->replace([
            'phone' => $phone,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{6,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Số điện thoại chỉ được chứa chữ số, tối đa 15 chữ số (có thể bắt đầu bằng +).',
            'phone.max' => 'Số điện thoại không được dài quá 20 ký tự.',
        ];
    }
}

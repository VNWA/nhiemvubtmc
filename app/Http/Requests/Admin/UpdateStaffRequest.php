<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $editing */
        $editing = $this->route('staff');

        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($editing->id),
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:1', 'confirmed'],
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
        ];
    }
}

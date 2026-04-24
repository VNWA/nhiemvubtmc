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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $editing */
        $editing = $this->route('user');

        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($editing->id),
            'password' => ['nullable', 'string', 'min:1', 'confirmed'],
            'role' => ['required', 'in:admin,user'],
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

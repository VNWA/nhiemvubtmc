<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdjustUserBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return $this->user()?->can('update', $target ?? new User) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'in:credit,debit,commission'],
            'amount_vnd' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

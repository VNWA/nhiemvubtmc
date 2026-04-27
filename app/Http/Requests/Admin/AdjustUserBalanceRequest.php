<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
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
            'operation' => ['required', 'in:credit,debit,commission,freeze,unfreeze'],
            'amount_vnd' => [
                'required',
                'integer',
                'min:1',
                'max:1000000000',
                $this->debitMustNotExceedBalance(...),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function debitMustNotExceedBalance(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ((string) $this->input('operation') !== 'debit') {
                return;
            }

            /** @var User|null $target */
            $target = $this->route('user');
            if (! $target instanceof User) {
                return;
            }

            $amount = (int) $value;
            $balance = (int) $target->balance_vnd;
            if ($amount > $balance) {
                $fail('Không thể trừ quá số dư hiện tại (tối đa '.number_format($balance, 0, ',', '.').' VNĐ).');
            }
        };
    }
}

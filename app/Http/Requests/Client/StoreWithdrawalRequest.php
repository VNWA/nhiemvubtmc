<?php

namespace App\Http\Requests\Client;

use App\Enums\WithdrawalStatus;
use App\Models\WithdrawalRequest;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_vnd' => [
                'required',
                'integer',
                'min:10000',
                'max:1000000000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $user = $this->user();
                    if ($user === null) {
                        return;
                    }

                    $availablePool = (int) $user->availableVnd();
                    $lockedPending = (int) WithdrawalRequest::query()
                        ->where('user_id', $user->getKey())
                        ->where('status', WithdrawalStatus::Pending->value)
                        ->sum('amount_vnd');
                    $available = max(0, $availablePool - $lockedPending);

                    if ((int) $value > $available) {
                        $fail(sprintf(
                            'Số tiền rút vượt quá số dư khả dụng (%s VNĐ).',
                            number_format($available, 0, ',', '.'),
                        ));
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount_vnd' => (int) ($this->input('amount_vnd') ?? 0),
            'note' => is_string($this->input('note')) ? trim($this->input('note')) : null,
        ]);
    }
}

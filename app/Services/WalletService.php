<?php

namespace App\Services;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /**
     * Apply a balance change to an already locked user inside an
     * outer DB transaction, persist the new balance, and record the
     * matching wallet transaction. The caller MUST have selected the
     * user with `lockForUpdate()` to guarantee correctness.
     *
     * @param  array<string, mixed>  $meta
     */
    public function apply(
        User $lockedUser,
        WalletDirection $direction,
        WalletSource $source,
        int $amountVnd,
        ?string $description = null,
        array $meta = [],
    ): WalletTransaction {
        if ($amountVnd <= 0) {
            throw new \InvalidArgumentException('Wallet amount must be positive.');
        }

        $current = (int) $lockedUser->balance_vnd;
        $delta = $direction->isCredit() ? $amountVnd : -$amountVnd;
        $newBalance = $current + $delta;

        if ($newBalance < 0) {
            throw ValidationException::withMessages([
                'amount_vnd' => ['Số dư không đủ (hiện còn '.number_format($current, 0, ',', '.').' VNĐ).'],
            ]);
        }

        $lockedUser->balance_vnd = $newBalance;
        $lockedUser->save();

        return WalletTransaction::create([
            'user_id' => $lockedUser->getKey(),
            'direction' => $direction,
            'source' => $source,
            'amount_vnd' => $amountVnd,
            'balance_after_vnd' => $newBalance,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}

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
        $this->capFrozenToBalance($lockedUser);
        $lockedUser->save();

        return WalletTransaction::create([
            'user_id' => $lockedUser->getKey(),
            'direction' => $direction,
            'source' => $source,
            'amount_vnd' => $amountVnd,
            'balance_after_vnd' => (int) $lockedUser->balance_vnd,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    /**
     * Move funds from the spendable pool into a frozen (locked) sub-balance. Total
     * `balance_vnd` is unchanged. Caller must have `lockForUpdate()` on the user row.
     *
     * @param  array<string, mixed>  $meta
     */
    public function freeze(
        User $lockedUser,
        int $amountVnd,
        ?string $description = null,
        array $meta = [],
    ): WalletTransaction {
        if ($amountVnd <= 0) {
            throw new \InvalidArgumentException('Wallet amount must be positive.');
        }

        $balance = (int) $lockedUser->balance_vnd;
        $frozen = (int) ($lockedUser->frozen_vnd ?? 0);
        $available = $balance - $frozen;

        if ($amountVnd > $available) {
            throw ValidationException::withMessages([
                'amount_vnd' => ['Số dư khả dụng không đủ (còn '.number_format(max(0, $available), 0, ',', '.').' VNĐ).'],
            ]);
        }

        $lockedUser->frozen_vnd = $frozen + $amountVnd;
        $lockedUser->save();

        $newFrozen = (int) $lockedUser->frozen_vnd;

        return WalletTransaction::create([
            'user_id' => $lockedUser->getKey(),
            'direction' => WalletDirection::Debit,
            'source' => WalletSource::AdminFreeze,
            'amount_vnd' => $amountVnd,
            'balance_after_vnd' => $balance,
            'description' => $description,
            'meta' => array_merge($meta, [
                'frozen_vnd_after' => $newFrozen,
                'available_vnd_after' => max(0, $balance - $newFrozen),
            ]),
        ]);
    }

    /**
     * Release funds from the frozen sub-balance back to spendable. Total
     * `balance_vnd` is unchanged. Caller must have `lockForUpdate()` on the user row.
     *
     * @param  array<string, mixed>  $meta
     */
    public function unfreeze(
        User $lockedUser,
        int $amountVnd,
        ?string $description = null,
        array $meta = [],
    ): WalletTransaction {
        if ($amountVnd <= 0) {
            throw new \InvalidArgumentException('Wallet amount must be positive.');
        }

        $frozen = (int) ($lockedUser->frozen_vnd ?? 0);

        if ($amountVnd > $frozen) {
            throw ValidationException::withMessages([
                'amount_vnd' => ['Số tiền đang đóng băng không đủ (còn '.number_format($frozen, 0, ',', '.').' VNĐ).'],
            ]);
        }

        $balance = (int) $lockedUser->balance_vnd;
        $lockedUser->frozen_vnd = $frozen - $amountVnd;
        $lockedUser->save();

        $newFrozen = (int) $lockedUser->frozen_vnd;

        return WalletTransaction::create([
            'user_id' => $lockedUser->getKey(),
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::AdminUnfreeze,
            'amount_vnd' => $amountVnd,
            'balance_after_vnd' => $balance,
            'description' => $description,
            'meta' => array_merge($meta, [
                'frozen_vnd_after' => $newFrozen,
                'available_vnd_after' => max(0, $balance - $newFrozen),
            ]),
        ]);
    }

    public function capFrozenToBalance(User $lockedUser): void
    {
        $balance = (int) $lockedUser->balance_vnd;
        $frozen = (int) ($lockedUser->frozen_vnd ?? 0);
        if ($frozen > $balance) {
            $lockedUser->frozen_vnd = $balance;
        }
    }
}

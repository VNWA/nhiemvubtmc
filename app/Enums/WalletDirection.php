<?php

namespace App\Enums;

enum WalletDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function isCredit(): bool
    {
        return $this === self::Credit;
    }

    public function isDebit(): bool
    {
        return $this === self::Debit;
    }
}

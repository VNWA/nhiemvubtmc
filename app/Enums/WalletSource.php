<?php

namespace App\Enums;

enum WalletSource: string
{
    case AdminCredit = 'admin_credit';
    case AdminDebit = 'admin_debit';
    case BetPlace = 'bet_place';
    case BetCancel = 'bet_cancel';

    public function label(): string
    {
        return match ($this) {
            self::AdminCredit => 'Admin nạp số dư',
            self::AdminDebit => 'Admin trừ số dư',
            self::BetPlace => 'Đặt cược',
            self::BetCancel => 'Huỷ cược (hoàn tiền)',
        };
    }
}

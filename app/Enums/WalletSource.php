<?php

namespace App\Enums;

enum WalletSource: string
{
    case AdminCredit = 'admin_credit';
    case AdminDebit = 'admin_debit';
    case Commission = 'commission';
    case BetPlace = 'bet_place';
    case BetCancel = 'bet_cancel';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::AdminCredit => 'Nạp tiền',
            self::AdminDebit => 'Trừ tiền',
            self::Commission => 'Thưởng hoa hồng',
            self::BetPlace => 'Đặt cược',
            self::BetCancel => 'Huỷ cược (hoàn tiền)',
            self::Withdrawal => 'Rút tiền đã duyệt',
        };
    }
}

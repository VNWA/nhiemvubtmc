<?php

namespace App\Enums;

enum WalletSource: string
{
    case AdminCredit = 'admin_credit';
    case AdminDebit = 'admin_debit';
    case Commission = 'commission';
    case BetPlace = 'bet_place';
    case BetCancel = 'bet_cancel';
    case EventRefund = 'event_refund';
    case Withdrawal = 'withdrawal';
    case AdminFreeze = 'admin_freeze';
    case AdminUnfreeze = 'admin_unfreeze';

    public function label(): string
    {
        return match ($this) {
            self::AdminCredit => 'Nạp tiền',
            self::AdminDebit => 'Trừ tiền',
            self::Commission => 'Thưởng hoa hồng',
            self::BetPlace => 'Phí tham gia sự kiện',
            self::BetCancel => 'Huỷ cược (hoàn tiền)',
            self::EventRefund => 'Hoàn trả sự kiện',
            self::Withdrawal => 'Rút tiền đã duyệt',
            self::AdminFreeze => 'Đóng băng số dư',
            self::AdminUnfreeze => 'Mở đóng băng',
        };
    }
}

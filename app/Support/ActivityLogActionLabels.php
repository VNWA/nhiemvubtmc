<?php

namespace App\Support;

/**
 * Nhãn hiển thị tiếng Việt cho từng mã hành động lưu tại cột activity_logs.action.
 * Dùng chung cho bảng nhật ký và bộ lọc loại thao tác.
 */
final class ActivityLogActionLabels
{
    /**
     * Mã hành động (slug) => nhãn hiển thị
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'user.created' => 'Tạo tài khoản mới',
            'user.updated' => 'Cập nhật thông tin tài khoản',
            'user.deleted' => 'Xóa tài khoản',
            'user.locked' => 'Khóa tài khoản (chặn đăng nhập)',
            'user.unlocked' => 'Mở khóa tài khoản',
            'user.login' => 'Đăng nhập vào hệ thống',
            'user.password_changed' => 'Đổi mật khẩu',
            'user.2fa_cleared' => 'Gỡ xác thực 2 bước (2FA)',
            'wallet.credit' => 'Nạp tiền (cộng số dư)',
            'wallet.debit' => 'Trừ tiền từ số dư',
            'wallet.commission' => 'Thưởng hoa hồng',
            'wallet.freeze' => 'Đóng băng một phần số dư',
            'wallet.unfreeze' => 'Mở đóng băng, hoàn số dư dùng được',
            'bank.updated' => 'Cập nhật tài khoản ngân hàng',
        ];
    }

    public static function label(string $action): string
    {
        return self::all()[$action] ?? sprintf('Hành động lạ: %s', $action);
    }
}

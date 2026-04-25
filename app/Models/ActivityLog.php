<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'target_user_id', 'action', 'description', 'meta', 'ip'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'user.created' => 'Tạo người dùng',
            'user.updated' => 'Cập nhật người dùng',
            'user.deleted' => 'Xóa người dùng',
            'user.locked' => 'Khóa tài khoản',
            'user.unlocked' => 'Mở khóa tài khoản',
            'user.login' => 'Đăng nhập',
            'user.password_changed' => 'Đổi mật khẩu',
            'wallet.credit' => 'Nạp tiền',
            'wallet.debit' => 'Trừ tiền',
            'wallet.commission' => 'Thưởng hoa hồng',
            'bank.updated' => 'Cập nhật ngân hàng',
            default => $this->action,
        };
    }
}

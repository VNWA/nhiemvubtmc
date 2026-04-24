<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'amount_vnd',
    'bank_name',
    'bank_account_number',
    'bank_account_name',
    'note',
    'status',
    'admin_note',
    'processed_by',
    'processed_at',
])]
class WithdrawalRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount_vnd' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @param  Builder<WithdrawalRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', WithdrawalStatus::Pending->value);
    }
}

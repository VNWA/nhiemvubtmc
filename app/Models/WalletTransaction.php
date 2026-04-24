<?php

namespace App\Models;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'direction',
    'source',
    'amount_vnd',
    'balance_after_vnd',
    'description',
    'meta',
])]
class WalletTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => WalletDirection::class,
            'source' => WalletSource::class,
            'amount_vnd' => 'integer',
            'balance_after_vnd' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

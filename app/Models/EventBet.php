<?php

namespace App\Models;

use App\Enums\EventBetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBet extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event_round_id',
        'option_id',
        'amount_vnd',
        'status',
        'refund_vnd',
        'commission_vnd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventBetStatus::class,
            'amount_vnd' => 'integer',
            'refund_vnd' => 'integer',
            'commission_vnd' => 'integer',
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
     * @return BelongsTo<EventRound, $this>
     */
    public function eventRound(): BelongsTo
    {
        return $this->belongsTo(EventRound::class, 'event_round_id');
    }

    /**
     * @return BelongsTo<EventRoomOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(EventRoomOption::class, 'option_id');
    }
}

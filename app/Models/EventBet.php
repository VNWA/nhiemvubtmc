<?php

namespace App\Models;

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
    ];

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

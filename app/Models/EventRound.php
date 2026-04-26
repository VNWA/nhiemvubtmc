<?php

namespace App\Models;

use App\Enums\EventRoundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRound extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_room_id',
        'round_session',
        'round_number',
        'name',
        'status',
        'duration_seconds',
        'started_at',
        'auto_end_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_session' => 'integer',
            'status' => EventRoundStatus::class,
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'auto_end_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventRoom, $this>
     */
    public function eventRoom(): BelongsTo
    {
        return $this->belongsTo(EventRoom::class, 'event_room_id');
    }

    /**
     * @return HasMany<EventBet, $this>
     */
    public function bets(): HasMany
    {
        return $this->hasMany(EventBet::class, 'event_round_id');
    }

    public function isOpen(): bool
    {
        return $this->status === EventRoundStatus::Open;
    }
}

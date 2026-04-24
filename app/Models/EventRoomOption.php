<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRoomOption extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_room_id',
        'label',
        'bg_color',
        'text_color',
        'sort_order',
    ];

    /**
     * @return BelongsTo<EventRoom, $this>
     */
    public function eventRoom(): BelongsTo
    {
        return $this->belongsTo(EventRoom::class, 'event_room_id');
    }
}

<?php

namespace App\Models;

use App\Enums\EventBetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class EventBet extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event_round_id',
        'selected_option_ids',
        'amount_vnd',
        'status',
        'refund_vnd',
        'commission_vnd',
        'refund_wallet_tx_id',
        'commission_wallet_tx_id',
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
            'selected_option_ids' => 'array',
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
     * Resolve the labels for every option this bet covers, using either an
     * options collection injected by the caller or a fresh DB query.
     *
     * @param  Collection<int, EventRoomOption>|null  $options
     * @return list<string>
     */
    public function selectedOptionLabels($options = null): array
    {
        $ids = collect($this->selected_option_ids ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $bag = $options ?? EventRoomOption::query()
            ->whereIn('id', $ids)
            ->get(['id', 'label'])
            ->keyBy('id');

        return $ids
            ->map(fn (int $id) => $bag->get($id)?->label)
            ->filter()
            ->values()
            ->all();
    }
}

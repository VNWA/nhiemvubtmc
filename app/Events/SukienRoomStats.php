<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Cập nhật số người đặt / tổng tiền cho admin trong phòng. */
class SukienRoomStats implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<array{optionId: int, betsCount: int, totalAmountVnd: int}>  $perOption
     */
    public function __construct(
        public int $eventRoomId,
        public int $eventRoundId,
        public int $betsCount,
        public int $totalAmountVnd,
        public array $perOption = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('sukien-room.'.$this->eventRoomId)];
    }

    public function broadcastAs(): string
    {
        return 'sukien.room.stats';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'eventRoomId' => $this->eventRoomId,
            'eventRoundId' => $this->eventRoundId,
            'betsCount' => $this->betsCount,
            'totalAmountVnd' => $this->totalAmountVnd,
            'perOption' => $this->perOption,
        ];
    }
}

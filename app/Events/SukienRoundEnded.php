<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SukienRoundEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventRoomId,
        public int $eventRoundId,
        public int $roundNumber,
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
        return 'sukien.round.ended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'eventRoomId' => $this->eventRoomId,
            'eventRoundId' => $this->eventRoundId,
            'roundNumber' => $this->roundNumber,
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SukienRoundStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{id: int, label: string, bg_color: string, text_color: string}  $presetOption
     */
    public function __construct(
        public int $eventRoomId,
        public int $eventRoundId,
        public int $roundNumber,
        public array $presetOption,
        public ?string $autoEndAt = null,
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
        return 'sukien.round.started';
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
            'presetOption' => $this->presetOption,
            'autoEndAt' => $this->autoEndAt,
        ];
    }
}

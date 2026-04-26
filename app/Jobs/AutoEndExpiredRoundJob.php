<?php

namespace App\Jobs;

use App\Enums\EventRoundStatus;
use App\Models\EventRound;
use App\Services\EventRoundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AutoEndExpiredRoundJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventRoundId) {}

    public function handle(EventRoundService $rounds): void
    {
        /** @var EventRound|null $round */
        $round = EventRound::query()->with('eventRoom')->whereKey($this->eventRoundId)->first();

        if ($round === null) {
            return;
        }

        if ($round->status !== EventRoundStatus::Open) {
            return;
        }

        if ($round->auto_end_at === null || $round->auto_end_at->isFuture()) {
            // Timer was extended/cleared after the job was scheduled, leave it alone.
            return;
        }

        // autoEndRound also triggers the rollover on the room when it
        // succesfully closes the round, so a single call covers both steps.
        $rounds->autoEndRound($round);
    }
}

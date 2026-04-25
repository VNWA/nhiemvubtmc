<?php

namespace App\Services;

use App\Enums\EventRoundStatus;
use App\Events\SukienRoundEnded;
use App\Events\SukienRoundStarted;
use App\Jobs\AutoEndExpiredRoundJob;
use App\Models\EventRoom;
use App\Models\EventRound;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventRoundService
{
    public const MIN_DURATION_SECONDS = 10;

    public const MAX_DURATION_SECONDS = 3600;

    public function startRound(
        EventRoom $room,
        User $admin,
        ?string $displayName = null,
        ?int $durationSeconds = null,
    ): EventRound {
        if (! $admin->hasRole('admin')) {
            abort(403);
        }

        if ($durationSeconds !== null) {
            if ($durationSeconds < self::MIN_DURATION_SECONDS || $durationSeconds > self::MAX_DURATION_SECONDS) {
                throw ValidationException::withMessages([
                    'duration_seconds' => [
                        sprintf(
                            'Thời lượng phải từ %d đến %d giây.',
                            self::MIN_DURATION_SECONDS,
                            self::MAX_DURATION_SECONDS,
                        ),
                    ],
                ]);
            }
        }

        $round = DB::transaction(function () use ($room, $displayName, $durationSeconds) {
            /** @var EventRoom $lockedRoom */
            $lockedRoom = EventRoom::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedRoom->is_active) {
                throw ValidationException::withMessages([
                    'round' => ['Phòng đang tạm dừng, không thể mở phiên mới.'],
                ]);
            }

            if (EventRound::query()->where('event_room_id', $lockedRoom->getKey())->where('status', EventRoundStatus::Open)->exists()) {
                throw ValidationException::withMessages([
                    'round' => ['Vui lòng kết thúc phiên hiện tại trước khi mở phiên mới.'],
                ]);
            }

            $nextNumber = (int) (EventRound::query()
                ->where('event_room_id', $lockedRoom->getKey())
                ->max('round_number') ?? 0) + 1;

            $name = $displayName !== null && trim($displayName) !== ''
                ? mb_substr(trim($displayName), 0, 120)
                : sprintf('Phiên #%d', $nextNumber);

            $startedAt = now();
            $autoEndAt = $durationSeconds !== null
                ? $startedAt->copy()->addSeconds($durationSeconds)
                : null;

            $round = EventRound::query()->create([
                'event_room_id' => $lockedRoom->getKey(),
                'round_number' => $nextNumber,
                'name' => $name,
                'status' => EventRoundStatus::Open,
                'duration_seconds' => $durationSeconds,
                'started_at' => $startedAt,
                'auto_end_at' => $autoEndAt,
                'ended_at' => null,
            ]);

            event(new SukienRoundStarted(
                (int) $lockedRoom->getKey(),
                (int) $round->getKey(),
                (int) $round->round_number,
                $autoEndAt?->toIso8601String(),
            ));

            return $round;
        });

        if ($round->auto_end_at !== null) {
            AutoEndExpiredRoundJob::dispatch((int) $round->getKey())
                ->delay($round->auto_end_at->copy()->addSecond());
        }

        return $round;
    }

    public function endRound(EventRound $round, User $admin): void
    {
        if (! $admin->hasRole('admin')) {
            abort(403);
        }

        $this->finalizeRound($round, throwIfClosed: true);
    }

    /**
     * Close a round without admin authorization. Used by the auto-end job
     * once the configured duration elapses; safe to call when the round is
     * already closed (it becomes a no-op).
     */
    public function autoEndRound(EventRound $round): void
    {
        $this->finalizeRound($round, throwIfClosed: false);
    }

    private function finalizeRound(EventRound $round, bool $throwIfClosed): void
    {
        DB::transaction(function () use ($round, $throwIfClosed) {
            /** @var EventRound $locked */
            $locked = EventRound::query()->whereKey($round->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== EventRoundStatus::Open) {
                if ($throwIfClosed) {
                    throw ValidationException::withMessages([
                        'round' => ['Phiên này đã kết thúc.'],
                    ]);
                }

                return;
            }

            $locked->status = EventRoundStatus::Closed;
            $locked->ended_at = Carbon::now();
            $locked->save();

            event(new SukienRoundEnded(
                (int) $locked->event_room_id,
                (int) $locked->getKey(),
                (int) $locked->round_number,
            ));
        });
    }
}

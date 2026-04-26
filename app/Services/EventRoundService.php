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

    public const MAX_DURATION_SECONDS = 900;

    public function startRound(
        EventRoom $room,
        User $admin,
        ?string $displayName = null,
        ?int $durationSeconds = null,
        bool $autoRollover = false,
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

        if ($autoRollover && $durationSeconds === null) {
            throw ValidationException::withMessages([
                'duration_seconds' => ['Cần đặt thời lượng để bật tự mở phiên tiếp theo.'],
            ]);
        }

        return $this->openRoundOnRoom(
            $room,
            $displayName,
            $durationSeconds,
            $autoRollover ? $durationSeconds : null,
        );
    }

    /**
     * Auto-open the next round in a rollover loop. No admin check — this is
     * only invoked from the queued AutoEndExpiredRoundJob right after the
     * previous round closed and we already verified the room still has
     * auto_rollover_seconds configured.
     */
    public function startRolloverRound(EventRoom $room): ?EventRound
    {
        $duration = $room->auto_rollover_seconds === null
            ? null
            : (int) $room->auto_rollover_seconds;

        if ($duration === null) {
            return null;
        }

        try {
            return $this->openRoundOnRoom($room, null, $duration, $duration);
        } catch (ValidationException) {
            // Room turned off, or another round is already open — drop the
            // rollover silently to avoid retry storms from the queue.
            return null;
        }
    }

    private function openRoundOnRoom(
        EventRoom $room,
        ?string $displayName,
        ?int $durationSeconds,
        ?int $rolloverSeconds,
    ): EventRound {
        $round = DB::transaction(function () use ($room, $displayName, $durationSeconds, $rolloverSeconds) {
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

            $session = (int) $lockedRoom->round_session;

            $nextNumber = (int) (EventRound::query()
                ->where('event_room_id', $lockedRoom->getKey())
                ->where('round_session', $session)
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
                'round_session' => $session,
                'round_number' => $nextNumber,
                'name' => $name,
                'status' => EventRoundStatus::Open,
                'duration_seconds' => $durationSeconds,
                'started_at' => $startedAt,
                'auto_end_at' => $autoEndAt,
                'ended_at' => null,
            ]);

            // Persist rollover preference on the room — the AutoEndExpiredRoundJob
            // reads this AFTER closing the round to decide whether to re-open.
            if ($lockedRoom->auto_rollover_seconds !== $rolloverSeconds) {
                $lockedRoom->auto_rollover_seconds = $rolloverSeconds;
                $lockedRoom->save();
            }

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

    /**
     * Tăng kỳ đếm phiên — phiên mới tới sẽ là Phiên #1 (khi không gõ tên tuỳ chỉnh).
     * Chỉ gọi khi không còn phiên đang mở.
     */
    public function resetRoundSession(EventRoom $room, User $admin): void
    {
        if (! $admin->hasRole('admin')) {
            abort(403);
        }

        DB::transaction(function () use ($room): void {
            /** @var EventRoom $locked */
            $locked = EventRoom::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            if (EventRound::query()->where('event_room_id', $locked->getKey())->where('status', EventRoundStatus::Open)->exists()) {
                throw ValidationException::withMessages([
                    'round' => ['Kết thúc phiên hiện tại trước khi reset đếm phiên.'],
                ]);
            }

            $locked->round_session = (int) $locked->round_session + 1;
            $locked->save();
        });
    }

    public function endRound(EventRound $round, User $admin): void
    {
        if (! $admin->hasRole('admin')) {
            abort(403);
        }

        $this->finalizeRound($round, throwIfClosed: true);
    }

    /**
     * Close a round without admin authorization. Used whenever an expired
     * round is observed (queue worker, HTTP lazy-close paths). Safe to call
     * when the round is already closed (it becomes a no-op). When the round
     * is actually transitioned and the room has auto-rollover configured,
     * the next round is opened immediately so the loop keeps running even
     * without an active queue worker.
     */
    public function autoEndRound(EventRound $round): void
    {
        $closedNow = $this->finalizeRound($round, throwIfClosed: false);

        if (! $closedNow) {
            return;
        }

        $room = $round->eventRoom?->fresh();
        if ($room === null) {
            return;
        }

        if ($room->auto_rollover_seconds === null || ! $room->is_active) {
            return;
        }

        $this->startRolloverRound($room);
    }

    private function finalizeRound(EventRound $round, bool $throwIfClosed): bool
    {
        return DB::transaction(function () use ($round, $throwIfClosed): bool {
            /** @var EventRound $locked */
            $locked = EventRound::query()->whereKey($round->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== EventRoundStatus::Open) {
                if ($throwIfClosed) {
                    throw ValidationException::withMessages([
                        'round' => ['Phiên này đã kết thúc.'],
                    ]);
                }

                return false;
            }

            $locked->status = EventRoundStatus::Closed;
            $locked->ended_at = Carbon::now();
            $locked->save();

            event(new SukienRoundEnded(
                (int) $locked->event_room_id,
                (int) $locked->getKey(),
                (int) $locked->round_number,
            ));

            return true;
        });
    }
}

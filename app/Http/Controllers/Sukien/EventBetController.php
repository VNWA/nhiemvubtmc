<?php

namespace App\Http\Controllers\Sukien;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Events\SukienRoomStats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sukien\StoreEventBetRequest;
use App\Models\EventBet;
use App\Models\EventRoom;
use App\Models\EventRoomOption;
use App\Models\EventRound;
use App\Models\User;
use App\Services\EventRoundService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventBetController extends Controller
{
    /**
     * Cut-off (seconds) before a round auto-ends within which the user is no
     * longer allowed to cancel — protects against last-second griefing once
     * the trend is visible.
     */
    private const CANCEL_LOCK_SECONDS = 5;

    public function __construct(
        private EventRoundService $rounds,
        private WalletService $wallet,
    ) {}

    public function store(StoreEventBetRequest $request, string $slug): JsonResponse|RedirectResponse
    {
        $room = EventRoom::query()->where('slug', $slug)->firstOrFail();
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $open = $room->openRound();
        if ($open === null) {
            throw ValidationException::withMessages(['bet' => ['Hiện không có phiên nào đang mở để tham gia.']]);
        }

        // Defensive auto-end: if the timer expired but the queue worker
        // hasn't fired the job yet, close the round before accepting bets.
        if ($open->auto_end_at !== null && $open->auto_end_at->isPast()) {
            $this->rounds->autoEndRound($open);
            throw ValidationException::withMessages(['bet' => ['Phiên này đã hết thời gian.']]);
        }

        /** @var list<int> $optionIds */
        $optionIds = collect($request->validated('option_ids'))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
        $totalAmount = (int) $request->validated('amount_vnd');

        $options = EventRoomOption::query()
            ->where('event_room_id', $room->getKey())
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        foreach ($optionIds as $optionId) {
            if (! $options->has($optionId)) {
                throw ValidationException::withMessages([
                    'option_ids' => ['Lựa chọn không thuộc sự kiện này.'],
                ]);
            }
        }

        DB::transaction(function () use ($user, $open, $optionIds, $options, $room, $totalAmount) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedUser->balance_vnd < $totalAmount) {
                throw ValidationException::withMessages([
                    'amount_vnd' => ['Số dư không đủ. Hiện còn '.number_format((int) $lockedUser->balance_vnd, 0, ',', '.').' VNĐ.'],
                ]);
            }

            $existing = EventBet::query()
                ->where('event_round_id', $open->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'option_ids' => ['Bạn đã tham gia phiên này rồi.'],
                ]);
            }

            $labels = collect($optionIds)
                ->map(fn (int $id) => $options->get($id)?->label)
                ->filter()
                ->values()
                ->all();

            $bet = EventBet::query()->create([
                'user_id' => $lockedUser->getKey(),
                'event_round_id' => $open->getKey(),
                'selected_option_ids' => $optionIds,
                'amount_vnd' => $totalAmount,
            ]);

            $this->wallet->apply(
                $lockedUser,
                WalletDirection::Debit,
                WalletSource::BetPlace,
                $totalAmount,
                'Tham gia phiên #'.$open->round_number.' - '.$room->name.' ('.implode(', ', $labels).')',
                [
                    'event_room_id' => (int) $room->getKey(),
                    'event_room_name' => $room->name,
                    'event_round_id' => (int) $open->getKey(),
                    'round_number' => (int) $open->round_number,
                    'option_ids' => $optionIds,
                    'option_labels' => $labels,
                    'bet_id' => (int) $bet->getKey(),
                ],
            );
        });

        $this->broadcastStats($open);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'balance_vnd' => (int) $user->fresh()->balance_vnd,
            ]);
        }

        return redirect()
            ->route('sukien.show', ['slug' => $slug])
            ->with('success', 'Đã ghi nhận tham gia.');
    }

    public function destroy(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $room = EventRoom::query()->where('slug', $slug)->firstOrFail();
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $open = $room->openRound();
        if ($open === null) {
            throw ValidationException::withMessages(['bet' => ['Hiện không có phiên nào đang mở.']]);
        }

        if ($open->auto_end_at !== null && $open->auto_end_at->isPast()) {
            $this->rounds->autoEndRound($open);
            throw ValidationException::withMessages(['bet' => ['Phiên này đã hết thời gian, không thể huỷ.']]);
        }

        if ($open->auto_end_at !== null) {
            $remainingMs = ($open->auto_end_at->getTimestamp() * 1000) - (int) (microtime(true) * 1000);
            if ($remainingMs <= self::CANCEL_LOCK_SECONDS * 1000) {
                throw ValidationException::withMessages([
                    'bet' => ['Còn dưới '.self::CANCEL_LOCK_SECONDS.' giây nên không thể huỷ tham gia.'],
                ]);
            }
        }

        DB::transaction(function () use ($user, $open, $room) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            /** @var EventBet|null $bet */
            $bet = EventBet::query()
                ->where('event_round_id', $open->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($bet === null) {
                throw ValidationException::withMessages(['bet' => ['Bạn chưa tham gia phiên này.']]);
            }

            $amount = (int) $bet->amount_vnd;
            $optionIds = collect($bet->selected_option_ids ?? [])->map(fn ($v) => (int) $v)->all();
            $optionLabels = $bet->selectedOptionLabels();
            $betId = (int) $bet->getKey();
            $bet->delete();

            $this->wallet->apply(
                $lockedUser,
                WalletDirection::Credit,
                WalletSource::BetCancel,
                $amount,
                'Huỷ tham gia phiên #'.$open->round_number.' - '.$room->name
                    .(empty($optionLabels) ? '' : ' ('.implode(', ', $optionLabels).')'),
                [
                    'event_room_id' => (int) $room->getKey(),
                    'event_room_name' => $room->name,
                    'event_round_id' => (int) $open->getKey(),
                    'round_number' => (int) $open->round_number,
                    'option_ids' => $optionIds,
                    'option_labels' => $optionLabels,
                    'bet_id' => $betId,
                ],
            );
        });

        $this->broadcastStats($open);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'balance_vnd' => (int) $user->fresh()->balance_vnd,
            ]);
        }

        return redirect()
            ->route('sukien.show', ['slug' => $slug])
            ->with('success', 'Đã huỷ tham gia, hoàn tiền vào số dư.');
    }

    private function broadcastStats(EventRound $round): void
    {
        $bets = EventBet::query()
            ->where('event_round_id', $round->getKey())
            ->get(['id', 'amount_vnd', 'selected_option_ids']);

        $betsCount = $bets->count();
        $totalAmount = (int) $bets->sum('amount_vnd');

        // For per-option fanout we count each ticket once for every option it
        // covers; "totalAmount" reflects the full ticket amount (no splitting).
        $perOption = [];
        foreach ($bets as $bet) {
            $ids = collect($bet->selected_option_ids ?? [])
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values();
            foreach ($ids as $id) {
                if (! isset($perOption[$id])) {
                    $perOption[$id] = ['optionId' => $id, 'betsCount' => 0, 'totalAmountVnd' => 0];
                }
                $perOption[$id]['betsCount']++;
                $perOption[$id]['totalAmountVnd'] += (int) $bet->amount_vnd;
            }
        }

        event(new SukienRoomStats(
            (int) $round->event_room_id,
            (int) $round->getKey(),
            $betsCount,
            $totalAmount,
            array_values($perOption),
        ));
    }
}

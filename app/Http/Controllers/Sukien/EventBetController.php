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
     * Khoảng thời gian (giây) trước khi kỳ tự kết thúc mà người chơi
     * không còn được phép huỷ đặt cược. Tránh trường hợp lợi dụng
     * huỷ vào phút chót khi đã thấy xu hướng kết quả.
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
            throw ValidationException::withMessages(['bet' => ['Hiện không có kỳ nào đang mở để đặt.']]);
        }

        // Defensive auto-end: if the timer expired but the queue worker
        // hasn't fired the job yet, close the round before accepting bets.
        if ($open->auto_end_at !== null && $open->auto_end_at->isPast()) {
            $this->rounds->autoEndRound($open);
            throw ValidationException::withMessages(['bet' => ['Kỳ này đã hết thời gian.']]);
        }

        $optionId = (int) $request->validated('option_id');
        $option = EventRoomOption::query()
            ->where('event_room_id', $room->getKey())
            ->whereKey($optionId)
            ->first();
        if ($option === null) {
            throw ValidationException::withMessages(['option_id' => ['Lựa chọn không thuộc sự kiện này.']]);
        }

        $amount = (int) $request->validated('amount_vnd');

        /** @var EventBet $bet */
        $bet = DB::transaction(function () use ($user, $open, $optionId, $amount, $option, $room) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedUser->balance_vnd < $amount) {
                throw ValidationException::withMessages([
                    'amount_vnd' => ['Số dư không đủ. Hiện còn '.number_format((int) $lockedUser->balance_vnd, 0, ',', '.').' VNĐ.'],
                ]);
            }

            if (EventBet::query()
                ->where('event_round_id', $open->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['bet' => ['Bạn đã đặt cho kỳ này rồi.']]);
            }

            $bet = EventBet::query()->create([
                'user_id' => $lockedUser->getKey(),
                'event_round_id' => $open->getKey(),
                'option_id' => $optionId,
                'amount_vnd' => $amount,
            ]);

            $this->wallet->apply(
                $lockedUser,
                WalletDirection::Debit,
                WalletSource::BetPlace,
                $amount,
                'Đặt cược kỳ #'.$open->round_number.' - '.$room->name,
                [
                    'event_room_id' => (int) $room->getKey(),
                    'event_room_name' => $room->name,
                    'event_round_id' => (int) $open->getKey(),
                    'round_number' => (int) $open->round_number,
                    'option_id' => (int) $option->getKey(),
                    'option_label' => $option->label,
                    'bet_id' => (int) $bet->getKey(),
                ],
            );

            return $bet;
        });

        $this->broadcastStats($open);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'bet' => [
                    'option_id' => (int) $bet->option_id,
                    'option_label' => $option->label,
                    'amount_vnd' => (int) $bet->amount_vnd,
                ],
                'balance_vnd' => (int) $user->fresh()->balance_vnd,
            ]);
        }

        return redirect()
            ->route('sukien.show', ['slug' => $slug])
            ->with('success', 'Đã ghi nhận đặt cược.');
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
            throw ValidationException::withMessages(['bet' => ['Hiện không có kỳ nào đang mở.']]);
        }

        if ($open->auto_end_at !== null && $open->auto_end_at->isPast()) {
            $this->rounds->autoEndRound($open);
            throw ValidationException::withMessages(['bet' => ['Kỳ này đã hết thời gian, không thể huỷ.']]);
        }

        if ($open->auto_end_at !== null) {
            $remainingMs = ($open->auto_end_at->getTimestamp() * 1000) - (int) (microtime(true) * 1000);
            if ($remainingMs <= self::CANCEL_LOCK_SECONDS * 1000) {
                throw ValidationException::withMessages([
                    'bet' => ['Còn dưới '.self::CANCEL_LOCK_SECONDS.' giây nên không thể huỷ đặt cược.'],
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
                ->with('option')
                ->lockForUpdate()
                ->first();

            if ($bet === null) {
                throw ValidationException::withMessages(['bet' => ['Bạn chưa đặt cược cho kỳ này.']]);
            }

            $amount = (int) $bet->amount_vnd;
            $optionLabel = $bet->option?->label;
            $betId = (int) $bet->getKey();
            $bet->delete();

            $this->wallet->apply(
                $lockedUser,
                WalletDirection::Credit,
                WalletSource::BetCancel,
                $amount,
                'Huỷ cược kỳ #'.$open->round_number.' - '.$room->name,
                [
                    'event_room_id' => (int) $room->getKey(),
                    'event_room_name' => $room->name,
                    'event_round_id' => (int) $open->getKey(),
                    'round_number' => (int) $open->round_number,
                    'option_label' => $optionLabel,
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
            ->with('success', 'Đã huỷ đặt cược, hoàn tiền vào số dư.');
    }

    private function broadcastStats(EventRound $round): void
    {
        $agg = EventBet::query()
            ->where('event_round_id', $round->getKey())
            ->selectRaw('count(*) as c, coalesce(sum(amount_vnd),0) as t')
            ->first();

        $perOption = EventBet::query()
            ->where('event_round_id', $round->getKey())
            ->selectRaw('option_id, count(*) as c, coalesce(sum(amount_vnd),0) as t')
            ->groupBy('option_id')
            ->get()
            ->map(fn ($row) => [
                'optionId' => (int) $row->option_id,
                'betsCount' => (int) $row->c,
                'totalAmountVnd' => (int) $row->t,
            ])
            ->values()
            ->all();

        event(new SukienRoomStats(
            (int) $round->event_room_id,
            (int) $round->getKey(),
            (int) ($agg->c ?? 0),
            (int) ($agg->t ?? 0),
            $perOption,
        ));
    }
}

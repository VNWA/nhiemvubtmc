<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventBetStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Models\EventBet;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserEventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private WalletService $wallet) {}

    public function index(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $bets = EventBet::query()
            ->with([
                'eventRound:id,event_room_id,round_number,name',
                'eventRound.eventRoom:id,name,slug',
            ])
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EventBet $bet) => $this->formatBet($bet));

        return Inertia::render('admin/users/Events', [
            'user' => [
                'id' => (int) $user->getKey(),
                'name' => $user->name,
                'username' => $user->username,
                'balance_vnd' => (int) $user->balance_vnd,
            ],
            'bets' => $bets,
            'statusOptions' => collect(EventBetStatus::cases())
                ->map(fn (EventBetStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
        ]);
    }

    public function update(Request $request, User $user, EventBet $bet): RedirectResponse
    {
        $this->authorize('update', $user);
        abort_unless((int) $bet->user_id === (int) $user->getKey(), 404);

        $request->merge([
            'refund_vnd' => $request->filled('refund_vnd') ? $request->input('refund_vnd') : 0,
            'commission_vnd' => $request->filled('commission_vnd') ? $request->input('commission_vnd') : 0,
        ]);

        $data = $request->validate([
            'status' => ['required', 'in:pending,completed'],
            'refund_vnd' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'commission_vnd' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $newStatus = EventBetStatus::from($data['status']);
        $newRefund = (int) ($data['refund_vnd'] ?? 0);
        $newCommission = (int) ($data['commission_vnd'] ?? 0);

        DB::transaction(function () use ($user, $bet, $newStatus, $newRefund, $newCommission, $request) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $adminId = (int) ($request->user()?->getKey() ?? 0);

            $this->upsertSettlement(
                $locked,
                $bet,
                'refund_wallet_tx_id',
                WalletSource::EventRefund,
                $newRefund,
                'Hoàn trả sự kiện · phiên #'.$bet->getKey(),
                $adminId,
            );

            $this->upsertSettlement(
                $locked,
                $bet,
                'commission_wallet_tx_id',
                WalletSource::Commission,
                $newCommission,
                'Hoa hồng sự kiện · phiên #'.$bet->getKey(),
                $adminId,
            );

            $bet->status = $newStatus;
            $bet->refund_vnd = $newRefund;
            $bet->commission_vnd = $newCommission;
            $bet->save();
        });

        return back()->with('success', 'Đã cập nhật phiên #'.$bet->getKey().'.');
    }

    /**
     * Keep a single wallet transaction in sync with the bet's settlement value.
     * Updating the bet (e.g. commission 150k -> 30k) rewrites the existing row
     * in place so the wallet history shows one consolidated entry.
     */
    private function upsertSettlement(
        User $lockedUser,
        EventBet $bet,
        string $fkColumn,
        WalletSource $source,
        int $newAmount,
        string $description,
        int $adminId,
    ): void {
        $existingId = $bet->{$fkColumn};
        $existing = $existingId ? WalletTransaction::query()->find($existingId) : null;
        $existingAmount = $existing ? (int) $existing->amount_vnd : 0;

        if ($existing !== null) {
            if ($newAmount === 0) {
                $this->shiftBalance($lockedUser, -$existingAmount);
                $existing->delete();
                $bet->{$fkColumn} = null;

                return;
            }

            $delta = $newAmount - $existingAmount;
            if ($delta !== 0) {
                $this->shiftBalance($lockedUser, $delta);
            }

            $existing->amount_vnd = $newAmount;
            $existing->balance_after_vnd = (int) $lockedUser->balance_vnd;
            $existing->description = $description;
            $existing->meta = array_merge(
                is_array($existing->meta) ? $existing->meta : [],
                ['admin_id' => $adminId, 'event_bet_id' => $bet->getKey()],
            );
            $existing->save();

            return;
        }

        if ($newAmount === 0) {
            return;
        }

        $tx = $this->wallet->apply(
            $lockedUser,
            WalletDirection::Credit,
            $source,
            $newAmount,
            $description,
            ['admin_id' => $adminId, 'event_bet_id' => $bet->getKey()],
        );
        $bet->{$fkColumn} = $tx->getKey();
    }

    private function shiftBalance(User $lockedUser, int $delta): void
    {
        $current = (int) $lockedUser->balance_vnd;
        $newBalance = $current + $delta;

        if ($newBalance < 0) {
            throw ValidationException::withMessages([
                'amount_vnd' => ['Số dư không đủ để giảm khoản này (hiện còn '.number_format($current, 0, ',', '.').' VNĐ).'],
            ]);
        }

        $lockedUser->balance_vnd = $newBalance;
        $lockedUser->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBet(EventBet $bet): array
    {
        $room = $bet->eventRound?->eventRoom;
        $round = $bet->eventRound;
        $fee = (int) $bet->amount_vnd;
        $refund = (int) ($bet->refund_vnd ?? 0);
        $commission = (int) ($bet->commission_vnd ?? 0);

        return [
            'id' => (int) $bet->getKey(),
            'amount_vnd' => $fee,
            'refund_vnd' => $refund,
            'commission_vnd' => $commission,
            'status' => $bet->status?->value ?? EventBetStatus::Pending->value,
            'status_label' => ($bet->status ?? EventBetStatus::Pending)->label(),
            'net_vnd' => $refund + $commission - $fee,
            'created_at' => $bet->created_at?->toIso8601String(),
            'option_labels' => $bet->selectedOptionLabels(),
            'round' => $round === null ? null : [
                'id' => (int) $round->getKey(),
                'number' => (int) $round->round_number,
                'name' => $round->name,
            ],
            'room' => $room === null ? null : [
                'id' => (int) $room->getKey(),
                'name' => $room->name,
                'slug' => $room->slug,
            ],
        ];
    }
}

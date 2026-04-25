<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventBetStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Models\EventBet;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'option:id,label',
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

        $data = $request->validate([
            'status' => ['required', 'in:pending,completed'],
            'refund_vnd' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'commission_vnd' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $newStatus = EventBetStatus::from($data['status']);
        $newRefund = (int) $data['refund_vnd'];
        $newCommission = (int) $data['commission_vnd'];

        $oldRefund = (int) ($bet->refund_vnd ?? 0);
        $oldCommission = (int) ($bet->commission_vnd ?? 0);

        $refundDelta = $newRefund - $oldRefund;
        $commissionDelta = $newCommission - $oldCommission;

        DB::transaction(function () use ($user, $bet, $newStatus, $newRefund, $newCommission, $refundDelta, $commissionDelta, $request) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $adminId = (int) ($request->user()?->getKey() ?? 0);

            if ($refundDelta !== 0) {
                $this->wallet->apply(
                    $locked,
                    $refundDelta > 0 ? WalletDirection::Credit : WalletDirection::Debit,
                    WalletSource::EventRefund,
                    abs($refundDelta),
                    'Hoàn trả sự kiện · phiên #'.$bet->getKey(),
                    ['admin_id' => $adminId, 'event_bet_id' => $bet->getKey()],
                );
            }

            if ($commissionDelta !== 0) {
                $this->wallet->apply(
                    $locked,
                    $commissionDelta > 0 ? WalletDirection::Credit : WalletDirection::Debit,
                    WalletSource::Commission,
                    abs($commissionDelta),
                    'Hoa hồng sự kiện · phiên #'.$bet->getKey(),
                    ['admin_id' => $adminId, 'event_bet_id' => $bet->getKey()],
                );
            }

            $bet->status = $newStatus;
            $bet->refund_vnd = $newRefund;
            $bet->commission_vnd = $newCommission;
            $bet->save();
        });

        return back()->with('success', 'Đã cập nhật phiên #'.$bet->getKey().'.');
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
            'option_label' => $bet->option?->label,
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

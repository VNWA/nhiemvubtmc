<?php

namespace App\Http\Controllers\Client;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreWithdrawalRequest;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $pending = WithdrawalRequest::query()
            ->where('user_id', $user->getKey())
            ->where('status', WithdrawalStatus::Pending->value)
            ->sum('amount_vnd');

        $history = WithdrawalRequest::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (WithdrawalRequest $r) => $this->formatRow($r))
            ->values()
            ->all();

        return Inertia::render('withdrawal/Create', [
            'balanceVnd' => (int) $user->balance_vnd,
            'pendingTotalVnd' => (int) $pending,
            'availableVnd' => max(0, (int) $user->balance_vnd - (int) $pending),
            'bank' => [
                'bank_name' => $user->bank_name,
                'bank_account_number' => $user->bank_account_number,
                'bank_account_name' => $user->bank_account_name,
            ],
            'history' => $history,
        ]);
    }

    public function store(StoreWithdrawalRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if (empty($user->bank_name) || empty($user->bank_account_number) || empty($user->bank_account_name)) {
            return back()->with('error', 'Bạn cần liên kết tài khoản ngân hàng trước khi rút tiền.');
        }

        $data = $request->validated();

        WithdrawalRequest::create([
            'user_id' => $user->getKey(),
            'amount_vnd' => (int) $data['amount_vnd'],
            'bank_name' => (string) $user->bank_name,
            'bank_account_number' => (string) $user->bank_account_number,
            'bank_account_name' => (string) $user->bank_account_name,
            'note' => $data['note'] ?? null,
            'status' => WithdrawalStatus::Pending,
        ]);

        return back()->with('success', 'Yêu cầu rút tiền đã được gửi. Vui lòng chờ admin duyệt.');
    }

    public function cancel(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $withdrawal->user_id === $user->getKey(), 403);

        if ($withdrawal->status !== WithdrawalStatus::Pending) {
            return back()->with('error', 'Yêu cầu này không thể huỷ.');
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Cancelled,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Đã huỷ yêu cầu rút tiền.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(WithdrawalRequest $r): array
    {
        return [
            'id' => $r->id,
            'amount_vnd' => (int) $r->amount_vnd,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'note' => $r->note,
            'admin_note' => $r->admin_note,
            'bank_name' => $r->bank_name,
            'bank_account_number' => $r->bank_account_number,
            'bank_account_name' => $r->bank_account_name,
            'created_at' => $r->created_at?->toIso8601String(),
            'processed_at' => $r->processed_at?->toIso8601String(),
            'can_cancel' => $r->status === WithdrawalStatus::Pending,
        ];
    }
}

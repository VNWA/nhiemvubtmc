<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $allowedStatuses = array_map(fn (WithdrawalStatus $s) => $s->value, WithdrawalStatus::cases());
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(5, min($perPage, 100));

        $query = WithdrawalRequest::query()
            ->with(['user:id,name,username', 'processor:id,name,username'])
            ->orderByDesc('created_at');

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        $items = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (WithdrawalRequest $r) => [
                'id' => $r->id,
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'username' => $r->user->username,
                ] : null,
                'amount_vnd' => (int) $r->amount_vnd,
                'bank_name' => $r->bank_name,
                'bank_account_number' => $r->bank_account_number,
                'bank_account_name' => $r->bank_account_name,
                'note' => $r->note,
                'admin_note' => $r->admin_note,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'processor' => $r->processor ? [
                    'id' => $r->processor->id,
                    'name' => $r->processor->name,
                ] : null,
                'processed_at' => $r->processed_at?->toIso8601String(),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $counts = WithdrawalRequest::query()
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(amount_vnd), 0) as sum_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('admin/withdrawals/Index', [
            'items' => $items,
            'filter' => [
                'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
                'per_page' => $perPage,
            ],
            'statusOptions' => array_map(
                fn (WithdrawalStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                WithdrawalStatus::cases(),
            ),
            'summary' => [
                'pending_total' => (int) ($counts[WithdrawalStatus::Pending->value]->sum_amount ?? 0),
                'pending_count' => (int) ($counts[WithdrawalStatus::Pending->value]->total ?? 0),
                'approved_total' => (int) ($counts[WithdrawalStatus::Approved->value]->sum_amount ?? 0),
                'approved_count' => (int) ($counts[WithdrawalStatus::Approved->value]->total ?? 0),
            ],
        ]);
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($withdrawal->status !== WithdrawalStatus::Pending) {
            return back()->with('error', 'Yêu cầu này đã được xử lý.');
        }

        $adminId = (int) ($request->user()?->getKey() ?? 0);

        DB::transaction(function () use ($withdrawal, $adminId, $data) {
            /** @var User $lockedUser */
            $lockedUser = User::query()
                ->whereKey($withdrawal->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->wallet->apply(
                $lockedUser,
                WalletDirection::Debit,
                WalletSource::Withdrawal,
                (int) $withdrawal->amount_vnd,
                sprintf('Rút tiền #%d về %s - %s', $withdrawal->id, $withdrawal->bank_name, $withdrawal->bank_account_number),
                [
                    'withdrawal_id' => $withdrawal->id,
                    'admin_id' => $adminId,
                ],
            );

            $withdrawal->update([
                'status' => WithdrawalStatus::Approved,
                'admin_note' => $data['admin_note'] ?? null,
                'processed_by' => $adminId ?: null,
                'processed_at' => now(),
            ]);
        });

        return back()->with('success', 'Đã duyệt yêu cầu rút tiền.');
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:500'],
        ]);

        if ($withdrawal->status !== WithdrawalStatus::Pending) {
            return back()->with('error', 'Yêu cầu này đã được xử lý.');
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Rejected,
            'admin_note' => $data['admin_note'],
            'processed_by' => (int) ($request->user()?->getKey() ?? 0) ?: null,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Đã từ chối yêu cầu rút tiền.');
    }
}

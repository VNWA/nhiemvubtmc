<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
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
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $isStaffNotAdmin = $this->isStaffNotAdmin($actor);

        $status = $request->string('status')->toString();
        $allowedStatuses = array_map(fn (WithdrawalStatus $s) => $s->value, WithdrawalStatus::cases());
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(5, min($perPage, 100));
        $searchQ = trim((string) $request->query('q', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = WithdrawalRequest::query()
            ->with(['user:id,name,username', 'processor:id,name,username'])
            ->orderByDesc('created_at');

        if ($isStaffNotAdmin) {
            $query->whereHas('user', function ($q) use ($actor) {
                $q->where('created_by', (int) $actor->getKey());
            });
        }

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        if ($searchQ !== '') {
            $like = '%'.str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\%', '\_'],
                mb_strtolower($searchQ, 'UTF-8')
            ).'%';
            $accountLike = '%'.str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\%', '\_'],
                $searchQ
            ).'%';
            $textFilter = function ($w) use ($like, $accountLike) {
                $w->whereHas('user', function ($u) use ($like) {
                    $u->where(function ($u2) use ($like) {
                        $u2->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(username) LIKE ?', [$like]);
                    });
                });
                $w->orWhereRaw('LOWER(COALESCE(withdrawal_requests.bank_name, \'\')) LIKE ?', [$like]);
                $w->orWhere('withdrawal_requests.bank_account_number', 'like', $accountLike);
                $w->orWhereRaw('LOWER(COALESCE(withdrawal_requests.bank_account_name, \'\')) LIKE ?', [$like]);
                $w->orWhereRaw('LOWER(COALESCE(withdrawal_requests.note, \'\')) LIKE ?', [$like]);
                $w->orWhereRaw('LOWER(COALESCE(withdrawal_requests.admin_note, \'\')) LIKE ?', [$like]);
            };
            if (ctype_digit($searchQ) && (int) $searchQ > 0) {
                $id = (int) $searchQ;
                $query->where(function ($w) use ($id, $textFilter) {
                    $w->where('withdrawal_requests.id', $id)
                        ->orWhere($textFilter);
                });
            } else {
                $query->where($textFilter);
            }
        }

        $displayTz = (string) config('app.display_timezone', 'Asia/Ho_Chi_Minh');
        if (! preg_match('/^[A-Za-z0-9_\/+\-]+$/', $displayTz)) {
            $displayTz = 'Asia/Ho_Chi_Minh';
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            try {
                $fromUtc = CarbonImmutable::createFromFormat('Y-m-d', $dateFrom, $displayTz)
                    ->startOfDay()
                    ->utc();
                $query->where('withdrawal_requests.created_at', '>=', $fromUtc);
            } catch (\Throwable) {
            }
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            try {
                $toUtc = CarbonImmutable::createFromFormat('Y-m-d', $dateTo, $displayTz)
                    ->endOfDay()
                    ->utc();
                $query->where('withdrawal_requests.created_at', '<=', $toUtc);
            } catch (\Throwable) {
            }
        }

        if ($request->filled('amount_min')) {
            $min = max(0, (int) $request->input('amount_min'));
            $query->where('withdrawal_requests.amount_vnd', '>=', $min);
        }

        if ($request->filled('amount_max')) {
            $max = min(1_000_000_000, (int) $request->input('amount_max'));
            if ($max >= 0) {
                $query->where('withdrawal_requests.amount_vnd', '<=', $max);
            }
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
                'processed_at' => $r->processed_at?->formatVn(),
                'created_at' => $r->created_at?->formatVn(),
            ]);

        $countsQuery = WithdrawalRequest::query();
        if ($isStaffNotAdmin) {
            $countsQuery->whereHas('user', function ($q) use ($actor) {
                $q->where('created_by', (int) $actor->getKey());
            });
        }
        $counts = $countsQuery
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(amount_vnd), 0) as sum_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('admin/withdrawals/Index', [
            'display_timezone' => $displayTz,
            'items' => $items,
            'filter' => [
                'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
                'per_page' => $perPage,
                'q' => $searchQ,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'amount_min' => $request->filled('amount_min') ? (string) (int) $request->input('amount_min') : '',
                'amount_max' => $request->filled('amount_max') ? (string) (int) $request->input('amount_max') : '',
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
        $this->assertStaffCanActOnWithdrawal($request, $withdrawal);

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
        $this->assertStaffCanActOnWithdrawal($request, $withdrawal);

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

    private function isStaffNotAdmin(User $user): bool
    {
        return $user->hasRole('staff') && ! $user->hasRole('admin');
    }

    private function assertStaffCanActOnWithdrawal(Request $request, WithdrawalRequest $withdrawal): void
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        if ($actor->hasRole('admin')) {
            return;
        }
        if (! $this->isStaffNotAdmin($actor)) {
            return;
        }
        $withdrawal->loadMissing('user');
        if ($withdrawal->user === null) {
            abort(403);
        }
        if ((int) $withdrawal->user->created_by !== (int) $actor->getKey()) {
            abort(403);
        }
    }
}

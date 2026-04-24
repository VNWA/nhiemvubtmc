<?php

namespace App\Http\Controllers\Client;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateAccountRequest;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    private const TRANSACTIONS_PER_PAGE = 15;

    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $totals = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->selectRaw("
                coalesce(sum(case when direction = 'credit' then amount_vnd else 0 end), 0) as total_credit,
                coalesce(sum(case when direction = 'debit' then amount_vnd else 0 end), 0) as total_debit,
                count(*) as total_count
            ")
            ->first();

        $latest = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (WalletTransaction $tx) => $this->formatTransaction($tx));

        return Inertia::render('account/Show', [
            'profile' => [
                'id' => (int) $user->getKey(),
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'role' => $user->roles->first()?->name ?? 'user',
            ],
            'balanceVnd' => (int) $user->balance_vnd,
            'totals' => [
                'totalCreditVnd' => (int) ($totals?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($totals?->total_debit ?? 0),
                'totalCount' => (int) ($totals?->total_count ?? 0),
            ],
            'recentTransactions' => $latest,
        ]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $data = $request->validated();
        $password = $data['password'] ?? null;
        $data = Arr::except($data, ['password', 'password_confirmation']);

        $user->fill($data);
        if (! empty($password)) {
            $user->password = $password;
        }
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã cập nhật thông tin tài khoản.']);

        return to_route('account.show');
    }

    public function wallet(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $filter = $this->normalizeFilter($request->query('filter'));

        $query = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at');

        $this->applyFilter($query, $filter);

        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $query)->count();
        $items = (clone $query)
            ->forPage($page, self::TRANSACTIONS_PER_PAGE)
            ->get()
            ->map(fn (WalletTransaction $tx) => $this->formatTransaction($tx));

        return Inertia::render('account/Wallet', [
            'balanceVnd' => (int) $user->balance_vnd,
            'transactions' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => self::TRANSACTIONS_PER_PAGE,
                'total' => $total,
                'hasMore' => $page * self::TRANSACTIONS_PER_PAGE < $total,
            ],
            'filter' => $filter,
            'sourceLabels' => $this->sourceLabelMap(),
        ]);
    }

    public function walletData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $filter = $this->normalizeFilter($request->query('filter'));

        $query = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at');

        $this->applyFilter($query, $filter);

        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $query)->count();
        $items = (clone $query)
            ->forPage($page, self::TRANSACTIONS_PER_PAGE)
            ->get()
            ->map(fn (WalletTransaction $tx) => $this->formatTransaction($tx));

        return response()->json([
            'data' => $items,
            'page' => $page,
            'perPage' => self::TRANSACTIONS_PER_PAGE,
            'total' => $total,
            'hasMore' => $page * self::TRANSACTIONS_PER_PAGE < $total,
            'filter' => $filter,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<WalletTransaction>  $query
     */
    private function applyFilter(\Illuminate\Database\Eloquent\Builder $query, string $filter): void
    {
        match ($filter) {
            'credit' => $query->where('direction', WalletDirection::Credit->value),
            'debit' => $query->where('direction', WalletDirection::Debit->value),
            default => null,
        };
    }

    private function normalizeFilter(mixed $raw): string
    {
        $value = is_string($raw) ? $raw : 'all';

        return in_array($value, ['all', 'credit', 'debit'], true) ? $value : 'all';
    }

    /**
     * @return array<string, string>
     */
    private function sourceLabelMap(): array
    {
        $map = [];
        foreach (WalletSource::cases() as $case) {
            $map[$case->value] = $case->label();
        }

        return $map;
    }

    /**
     * @return array{id: int, direction: string, source: string, source_label: string, amount_vnd: int, balance_after_vnd: int, description: ?string, created_at: ?string, meta: array<string, mixed>}
     */
    private function formatTransaction(WalletTransaction $tx): array
    {
        return [
            'id' => (int) $tx->getKey(),
            'direction' => $tx->direction->value,
            'source' => $tx->source->value,
            'source_label' => $tx->source->label(),
            'amount_vnd' => (int) $tx->amount_vnd,
            'balance_after_vnd' => (int) $tx->balance_after_vnd,
            'description' => $tx->description,
            'created_at' => $tx->created_at?->toIso8601String(),
            'meta' => is_array($tx->meta) ? $tx->meta : [],
        ];
    }
}

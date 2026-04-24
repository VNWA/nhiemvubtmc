<?php

namespace App\Http\Controllers\Client;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateAccountRequest;
use App\Http\Requests\Client\UpdateBankAccountRequest;
use App\Http\Requests\Client\UpdatePasswordRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    private const TRANSACTIONS_PER_PAGE = 15;

    /**
     * Hub menu page — lightweight; individual sub-pages own their forms.
     */
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

        return Inertia::render('account/Show', [
            'profile' => $this->profilePayload($user),
            'balanceVnd' => (int) $user->balance_vnd,
            'bank' => $this->bankPayload($user),
            'totals' => [
                'totalCreditVnd' => (int) ($totals?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($totals?->total_debit ?? 0),
                'totalCount' => (int) ($totals?->total_count ?? 0),
            ],
        ]);
    }

    public function editProfile(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('account/Profile', [
            'profile' => $this->profilePayload($user),
        ]);
    }

    public function updateProfile(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->fill($request->validated());
        $user->save();

        return to_route('account.profile.edit')->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function editPassword(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('account/Password');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->password = $request->validated('password');
        $user->save();

        return to_route('account.password.edit')->with('success', 'Đã đổi mật khẩu.');
    }

    public function editBank(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('account/BankAccount', [
            'bank' => $this->bankPayload($user),
            'bankOptions' => $this->bankOptions(),
        ]);
    }

    public function updateBank(UpdateBankAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->fill($request->validated());
        $user->save();

        return to_route('account.bank.edit')->with('success', 'Đã cập nhật thông tin ngân hàng.');
    }

    public function report(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $base = WalletTransaction::query()->where('user_id', $user->getKey());

        $totals = (clone $base)
            ->selectRaw("
                coalesce(sum(case when direction = 'credit' then amount_vnd else 0 end), 0) as total_credit,
                coalesce(sum(case when direction = 'debit' then amount_vnd else 0 end), 0) as total_debit,
                count(*) as total_count
            ")
            ->first();

        $bySource = (clone $base)
            ->selectRaw('source, direction, count(*) as c, coalesce(sum(amount_vnd), 0) as t')
            ->groupBy('source', 'direction')
            ->get()
            ->map(function ($row) {
                $source = $row->source instanceof WalletSource
                    ? $row->source
                    : WalletSource::from((string) $row->source);
                $direction = $row->direction instanceof WalletDirection
                    ? $row->direction
                    : WalletDirection::from((string) $row->direction);

                return [
                    'source' => $source->value,
                    'source_label' => $source->label(),
                    'direction' => $direction->value,
                    'count' => (int) $row->c,
                    'total_vnd' => (int) $row->t,
                ];
            })
            ->values();

        $last30DaysBase = (clone $base)->where('created_at', '>=', now()->subDays(30));
        $last30 = (clone $last30DaysBase)
            ->selectRaw("
                coalesce(sum(case when direction = 'credit' then amount_vnd else 0 end), 0) as total_credit,
                coalesce(sum(case when direction = 'debit' then amount_vnd else 0 end), 0) as total_debit,
                count(*) as total_count
            ")
            ->first();

        return Inertia::render('account/Report', [
            'balanceVnd' => (int) $user->balance_vnd,
            'totals' => [
                'totalCreditVnd' => (int) ($totals?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($totals?->total_debit ?? 0),
                'totalCount' => (int) ($totals?->total_count ?? 0),
            ],
            'last30Days' => [
                'totalCreditVnd' => (int) ($last30?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($last30?->total_debit ?? 0),
                'totalCount' => (int) ($last30?->total_count ?? 0),
            ],
            'bySource' => $bySource,
        ]);
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
     * @param  Builder<WalletTransaction>  $query
     */
    private function applyFilter(Builder $query, string $filter): void
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
     * @return array{id: int, name: string, username: string, email: string, created_at: ?string, role: string}
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'created_at' => $user->created_at?->toIso8601String(),
            'role' => $user->roles->first()?->name ?? 'user',
        ];
    }

    /**
     * @return array{bank_name: ?string, bank_account_number: ?string, bank_account_name: ?string}
     */
    private function bankPayload(User $user): array
    {
        return [
            'bank_name' => $user->bank_name,
            'bank_account_number' => $user->bank_account_number,
            'bank_account_name' => $user->bank_account_name,
        ];
    }

    /**
     * @return list<string>
     */
    private function bankOptions(): array
    {
        return [
            'Vietcombank',
            'VietinBank',
            'BIDV',
            'Agribank',
            'Techcombank',
            'MB Bank',
            'ACB',
            'VPBank',
            'Sacombank',
            'TPBank',
            'HDBank',
            'SHB',
            'OCB',
            'SeABank',
            'VIB',
            'MSB',
            'Eximbank',
            'LienVietPostBank',
            'NamABank',
            'DongABank',
            'SCB',
            'BacABank',
            'KienLongBank',
            'PVcomBank',
            'Cake by VPBank',
            'Timo',
        ];
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

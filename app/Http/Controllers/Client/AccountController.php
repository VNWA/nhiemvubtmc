<?php

namespace App\Http\Controllers\Client;

use App\Enums\EventBetStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdatePasswordRequest;
use App\Models\EventBet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    private const TRANSACTIONS_PER_PAGE = 15;

    /**
     * @return array{balanceVnd: int, frozenVnd: int, availableVnd: int}
     */
    private function walletSnapshot(User $user): array
    {
        $balanceVnd = (int) $user->balance_vnd;
        $frozenVnd = (int) ($user->frozen_vnd ?? 0);

        return [
            'balanceVnd' => $balanceVnd,
            'frozenVnd' => $frozenVnd,
            'availableVnd' => $user->availableVnd(),
        ];
    }

    /**
     * Hub menu page — lightweight; individual sub-pages own their forms.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $wallet = $this->walletSnapshot($user);

        $commissionSource = WalletSource::Commission->value;
        $unfreezeSource = WalletSource::AdminUnfreeze->value;
        $freezeSource = WalletSource::AdminFreeze->value;
        $totals = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->selectRaw("
                coalesce(sum(case
                    when direction = 'credit' and source not in ('{$commissionSource}', '{$unfreezeSource}')
                    then amount_vnd else 0 end), 0) as total_credit,
                coalesce(sum(case
                    when direction = 'debit' and source <> '{$freezeSource}'
                    then amount_vnd else 0 end), 0) as total_debit,
                coalesce(sum(case when source = '{$commissionSource}' then amount_vnd else 0 end), 0) as total_commission,
                count(*) as total_count
            ")
            ->first();

        $eventCount = EventBet::query()
            ->where('user_id', $user->getKey())
            ->count();

        return Inertia::render('account/Show', [
            'profile' => $this->profilePayload($user),
            ...$wallet,
            'bank' => $this->bankPayload($user),
            'totals' => [
                'totalCreditVnd' => (int) ($totals?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($totals?->total_debit ?? 0),
                'totalCommissionVnd' => (int) ($totals?->total_commission ?? 0),
                'totalCount' => (int) ($totals?->total_count ?? 0),
            ],
            'eventCount' => (int) $eventCount,
        ]);
    }

    public function events(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $bets = EventBet::query()
            ->with([
                'eventRound:id,event_room_id,round_number,name',
                'eventRound.eventRoom:id,name,slug',
            ])
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (EventBet $bet) {
                $fee = (int) $bet->amount_vnd;
                $refund = (int) ($bet->refund_vnd ?? 0);
                $commission = (int) ($bet->commission_vnd ?? 0);
                $status = $bet->status ?? EventBetStatus::Pending;

                return [
                    'id' => (int) $bet->getKey(),
                    'amount_vnd' => $fee,
                    'refund_vnd' => $refund,
                    'commission_vnd' => $commission,
                    'net_vnd' => $refund + $commission - $fee,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'created_at' => $bet->created_at?->formatVn(),
                    'option_labels' => $bet->selectedOptionLabels(),
                    'round_name' => $bet->eventRound?->name,
                    'round_number' => (int) ($bet->eventRound?->round_number ?? 0),
                    'room_name' => $bet->eventRound?->eventRoom?->name,
                ];
            });

        $wallet = $this->walletSnapshot($user);

        return Inertia::render('account/Events', [
            'bets' => $bets,
            ...$wallet,
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

    public function editPassword(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('account/Password');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->syncPasswordAndHintFromPlain($request->validated('password'));

        return to_route('account.password.edit')->with('success', 'Đã đổi mật khẩu.');
    }

    public function editBank(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('account/BankAccount', [
            'bank' => $this->bankPayload($user),
        ]);
    }

    public function report(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $base = WalletTransaction::query()->where('user_id', $user->getKey());

        $commissionSource = WalletSource::Commission->value;
        $unfreezeSource = WalletSource::AdminUnfreeze->value;
        $freezeSource = WalletSource::AdminFreeze->value;
        $totals = (clone $base)
            ->selectRaw("
                coalesce(sum(case
                    when direction = 'credit' and source not in ('{$commissionSource}', '{$unfreezeSource}')
                    then amount_vnd else 0 end), 0) as total_credit,
                coalesce(sum(case
                    when direction = 'debit' and source <> '{$freezeSource}'
                    then amount_vnd else 0 end), 0) as total_debit,
                coalesce(sum(case when source = '{$commissionSource}' then amount_vnd else 0 end), 0) as total_commission,
                coalesce(sum(case when source = '{$commissionSource}' then 1 else 0 end), 0) as commission_count,
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
                coalesce(sum(case
                    when direction = 'credit' and source not in ('{$commissionSource}', '{$unfreezeSource}')
                    then amount_vnd else 0 end), 0) as total_credit,
                coalesce(sum(case
                    when direction = 'debit' and source <> '{$freezeSource}'
                    then amount_vnd else 0 end), 0) as total_debit,
                coalesce(sum(case when source = '{$commissionSource}' then amount_vnd else 0 end), 0) as total_commission,
                count(*) as total_count
            ")
            ->first();

        $wallet = $this->walletSnapshot($user);

        return Inertia::render('account/Report', [
            ...$wallet,
            'totals' => [
                'totalCreditVnd' => (int) ($totals?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($totals?->total_debit ?? 0),
                'totalCommissionVnd' => (int) ($totals?->total_commission ?? 0),
                'commissionCount' => (int) ($totals?->commission_count ?? 0),
                'totalCount' => (int) ($totals?->total_count ?? 0),
            ],
            'last30Days' => [
                'totalCreditVnd' => (int) ($last30?->total_credit ?? 0),
                'totalDebitVnd' => (int) ($last30?->total_debit ?? 0),
                'totalCommissionVnd' => (int) ($last30?->total_commission ?? 0),
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
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $this->applyFilter($query, $filter);

        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $query)->count();
        $lastPage = $total > 0 ? (int) max(1, (int) ceil($total / self::TRANSACTIONS_PER_PAGE)) : 1;
        $page = min($page, $lastPage);

        $rows = (clone $query)
            ->forPage($page, self::TRANSACTIONS_PER_PAGE)
            ->get();
        $items = $this->sortWalletTransactionsForDisplay($rows)
            ->map(fn (WalletTransaction $tx) => $this->formatTransaction($tx));

        $wallet = $this->walletSnapshot($user);

        return Inertia::render('account/Wallet', [
            ...$wallet,
            'listTotals' => $this->computeWalletListTotals($user),
            'transactions' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => self::TRANSACTIONS_PER_PAGE,
                'total' => $total,
                'lastPage' => $lastPage,
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
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $this->applyFilter($query, $filter);

        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $query)->count();
        $lastPage = $total > 0 ? (int) max(1, (int) ceil($total / self::TRANSACTIONS_PER_PAGE)) : 1;
        $page = min($page, $lastPage);

        $rows = (clone $query)
            ->forPage($page, self::TRANSACTIONS_PER_PAGE)
            ->get();
        $items = $this->sortWalletTransactionsForDisplay($rows)
            ->map(fn (WalletTransaction $tx) => $this->formatTransaction($tx));

        return response()->json([
            'data' => $items,
            'page' => $page,
            'perPage' => self::TRANSACTIONS_PER_PAGE,
            'total' => $total,
            'lastPage' => $lastPage,
            'filter' => $filter,
        ]);
    }

    /**
     * @param  Builder<WalletTransaction>  $query
     */
    private function applyFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'credit' => $query
                ->where('direction', WalletDirection::Credit->value)
                ->where('source', WalletSource::AdminCredit->value),
            'refund' => $query->whereIn('source', [
                WalletSource::BetCancel->value,
                WalletSource::EventRefund->value,
            ]),
            'debit' => $query
                ->where('direction', WalletDirection::Debit->value)
                ->where('source', '<>', WalletSource::BetPlace->value)
                ->where('source', '<>', WalletSource::AdminFreeze->value),
            'commission' => $query->where('source', WalletSource::Commission->value),
            'bet_place' => $query->where('source', WalletSource::BetPlace->value),
            'freeze' => $query->whereIn('source', [
                WalletSource::AdminFreeze->value,
                WalletSource::AdminUnfreeze->value,
            ]),
            default => null,
        };
    }

    /**
     * Tổng theo cả lịch sử (một lần truy vấn) — dùng cho thẻ tóm tắt, không phụ thuộc bộ lọc/trang.
     *
     * @return array{
     *   adminCreditVnd: int,
     *   refundVnd: int,
     *   betPlaceVnd: int,
     *   commissionVnd: int,
     *   outDebitVnd: int,
     *   freezeVnd: int
     * }
     */
    private function computeWalletListTotals(User $user): array
    {
        $c = WalletSource::AdminCredit->value;
        $bc = WalletSource::BetCancel->value;
        $er = WalletSource::EventRefund->value;
        $bp = WalletSource::BetPlace->value;
        $cm = WalletSource::Commission->value;
        $ad = WalletSource::AdminDebit->value;
        $w = WalletSource::Withdrawal->value;
        $d = WalletDirection::Debit->value;

        $row = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('
                coalesce(sum(case when source = ? then amount_vnd else 0 end), 0) as admin_credit,
                coalesce(sum(case when source in (?, ?) then amount_vnd else 0 end), 0) as refund,
                coalesce(sum(case when source = ? then amount_vnd else 0 end), 0) as bet_place,
                coalesce(sum(case when source = ? then amount_vnd else 0 end), 0) as commission,
                coalesce(sum(case when direction = ? and source in (?, ?) then amount_vnd else 0 end), 0) as out_debit
            ', [$c, $bc, $er, $bp, $cm, $d, $ad, $w])
            ->first();

        return [
            'adminCreditVnd' => (int) ($row->admin_credit ?? 0),
            'refundVnd' => (int) ($row->refund ?? 0),
            'betPlaceVnd' => (int) ($row->bet_place ?? 0),
            'commissionVnd' => (int) ($row->commission ?? 0),
            'outDebitVnd' => (int) ($row->out_debit ?? 0),
            'freezeVnd' => (int) ($user->frozen_vnd ?? 0),
        ];
    }

    private function normalizeFilter(mixed $raw): string
    {
        $value = is_string($raw) ? $raw : 'all';

        return in_array(
            $value,
            ['all', 'credit', 'refund', 'debit', 'commission', 'bet_place', 'freeze'],
            true,
        ) ? $value : 'all';
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
     * @return array{id: int, name: string, username: string, email: string, phone: ?string, created_at: ?string, role: string}
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'created_at' => $user->created_at?->formatVn(),
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
     * @return array{id: int, direction: string, source: string, source_label: string, amount_vnd: int, balance_after_vnd: int, description: ?string, created_at: ?string, updated_at: ?string, meta: array<string, mixed>}
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
            'created_at' => $tx->created_at?->formatVn(),
            'updated_at' => $tx->updated_at?->formatVn(),
            'meta' => is_array($tx->meta) ? $tx->meta : [],
        ];
    }

    /**
     * Khóa gom các dòng cùng một cược/phiên: `meta.event_bet_id` (hoàn/hoa hồng) hoặc `meta.bet_id` (phí tham gia).
     */
    private function walletEventBetKey(WalletTransaction $tx): int
    {
        $m = $tx->meta;
        if (! is_array($m)) {
            return 0;
        }
        $eb = isset($m['event_bet_id']) ? (int) $m['event_bet_id'] : 0;
        if ($eb > 0) {
            return $eb;
        }
        $bid = isset($m['bet_id']) ? (int) $m['bet_id'] : 0;

        return $bid > 0 ? $bid : 0;
    }

    /**
     * Thứ tự đọc trong cùng một phiên: tham gia → hoàn/trả → hoa hồng.
     */
    private function walletEventFlowRank(WalletTransaction $tx): int
    {
        return match ($tx->source) {
            WalletSource::BetPlace => 1,
            WalletSource::BetCancel, WalletSource::EventRefund => 2,
            WalletSource::Commission => 3,
            default => 50,
        };
    }

    /**
     * Sắp xếp trang lịch sử: cụm cùng `walletEventBetKey` theo mốc cập nhật mới nhất trong trang,
     * trong cụm luôn bet_place → hoàn/trả → hoa hồng; các giao dịch khác theo `updated_at` giảm dần.
     *
     * @param  EloquentCollection<int, WalletTransaction>  $rows
     * @return Collection<int, WalletTransaction>
     */
    private function sortWalletTransactionsForDisplay(EloquentCollection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows->collect();
        }

        $maxUpdatedByBet = [];
        foreach ($rows as $tx) {
            $k = $this->walletEventBetKey($tx);
            if ($k === 0) {
                continue;
            }
            $u = $tx->updated_at?->getTimestamp() ?? 0;
            $maxUpdatedByBet[$k] = max($maxUpdatedByBet[$k] ?? 0, $u);
        }

        return $rows->sort(function (WalletTransaction $a, WalletTransaction $b) use ($maxUpdatedByBet): int {
            $ka = $this->walletEventBetKey($a);
            $kb = $this->walletEventBetKey($b);
            $ca = $ka > 0 ? ($maxUpdatedByBet[$ka] ?? 0) : ($a->updated_at?->getTimestamp() ?? 0);
            $cb = $kb > 0 ? ($maxUpdatedByBet[$kb] ?? 0) : ($b->updated_at?->getTimestamp() ?? 0);
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }

            if ($ka === $kb && $ka > 0) {
                $ra = $this->walletEventFlowRank($a);
                $rb = $this->walletEventFlowRank($b);
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
            } elseif ($ka !== $kb) {
                return $kb <=> $ka;
            }

            $ua = $a->updated_at?->getTimestamp() ?? 0;
            $ub = $b->updated_at?->getTimestamp() ?? 0;
            if ($ua !== $ub) {
                return $ub <=> $ua;
            }

            return (int) $b->getKey() <=> (int) $a->getKey();
        })->values();
    }
}

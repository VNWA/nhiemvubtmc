<?php

namespace App\Services;

use App\Enums\EventRoundStatus;
use App\Enums\UserStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Enums\WithdrawalStatus;
use App\Models\ActivityLog;
use App\Models\EventBet;
use App\Models\EventRound;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $actor, string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $isAdmin = $actor->hasRole('admin');
        $isStaffOnly = $actor->hasRole('staff') && ! $isAdmin;

        [$start, $end] = $this->resolveRange($period, $dateFrom, $dateTo);

        $customerQuery = $this->customersQuery($actor);
        $customerIdsSub = $customerQuery->clone()->select('users.id');

        $quick = [
            'total_customers' => (int) $customerQuery->clone()->count(),
            'total_staff' => $isAdmin ? $this->countStaffUsers() : null,
            'open_event_rounds' => (int) EventRound::query()
                ->where('status', EventRoundStatus::Open->value)
                ->count(),
            'pending_withdrawals' => (int) WithdrawalRequest::query()
                ->pending()
                ->whereIn('user_id', $customerIdsSub)
                ->count(),
            'total_balance_vnd' => (int) $customerQuery->clone()->sum('balance_vnd'),
            'period_deposit_vnd' => (int) $this->sumWalletInRange(
                $customerIdsSub,
                $start,
                $end,
                WalletSource::AdminCredit,
                WalletDirection::Credit,
            ),
            'period_withdrawal_vnd' => (int) $this->sumApprovedWithdrawalsInRange($customerIdsSub, $start, $end),
            // Trừ tiền trực tiếp bởi admin/nhân viên (không gồm rút tiền do user tạo lệnh).
            'period_admin_debit_vnd' => (int) $this->sumWalletInRange(
                $customerIdsSub,
                $start,
                $end,
                WalletSource::AdminDebit,
                WalletDirection::Debit,
            ),
            'period_commission_vnd' => (int) $this->sumWalletInRange(
                $customerIdsSub,
                $start,
                $end,
                WalletSource::Commission,
                WalletDirection::Credit,
            ),
        ];

        $overview = [
            'new_customers_in_period' => (int) $customerQuery->clone()
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'active_customers' => (int) $customerQuery->clone()
                ->where('status', UserStatus::Active->value)
                ->count(),
            'locked_customers' => (int) $customerQuery->clone()
                ->where('status', UserStatus::Locked->value)
                ->count(),
        ];

        $chartSeries = $this->buildChartSeries(
            $actor,
            $customerIdsSub,
            $start,
            $end,
        );

        $openRoomsCount = (int) EventRound::query()
            ->where('status', EventRoundStatus::Open->value)
            ->count();

        $pendingWithdrawalCount = (int) WithdrawalRequest::query()
            ->pending()
            ->whereIn('user_id', $customerIdsSub)
            ->count();

        $recentWithdrawals = WithdrawalRequest::query()
            ->with([
                'user:id,name,username',
                'processor:id,name,username',
                'processor.roles',
            ])
            ->whereIn('user_id', $customerIdsSub)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (WithdrawalRequest $r) => [
                'id' => (int) $r->getKey(),
                'user' => $r->user ? [
                    'id' => (int) $r->user->getKey(),
                    'name' => $r->user->name,
                    'username' => $r->user->username,
                ] : null,
                'amount_vnd' => (int) $r->amount_vnd,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'created_at' => $r->created_at?->formatVn(),
                'processor' => $this->serializeStaffActor($r->processor),
            ]);

        $recentUsers = $customerQuery->clone()
            ->with([
                'creator:id,name,username',
                'creator.roles',
            ])
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'name', 'username', 'created_at', 'created_by'])
            ->map(fn (User $u) => [
                'id' => (int) $u->getKey(),
                'name' => $u->name,
                'username' => $u->username,
                'created_at' => $u->created_at?->formatVn(),
                'creator' => $this->serializeStaffActor($u->creator),
            ]);

        $activities = $this->recentActivitiesQuery($actor, $customerIdsSub, $isAdmin)
            ->with(['actor:id,name,username', 'target:id,name,username'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => (int) $log->getKey(),
                'action' => $log->action,
                'action_label' => $log->actionLabel(),
                'description' => $log->description,
                'created_at' => $log->created_at?->formatVn(),
                'actor' => $log->actor === null ? null : [
                    'id' => (int) $log->actor->getKey(),
                    'name' => $log->actor->name,
                    'username' => $log->actor->username,
                ],
                'target' => $log->target === null ? null : [
                    'id' => (int) $log->target->getKey(),
                    'name' => $log->target->name,
                    'username' => $log->target->username,
                ],
            ]);

        return [
            'scope' => [
                'is_admin' => $isAdmin,
                'is_staff_only' => $isStaffOnly,
            ],
            'period' => [
                'key' => $period,
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'label' => $this->periodLabel($period, $start, $end, $dateFrom, $dateTo),
            ],
            'quick' => $quick,
            'overview' => $overview,
            'chart_series' => $chartSeries,
            'operations' => [
                'pending_withdrawals' => $pendingWithdrawalCount,
                'open_event_rounds' => $openRoomsCount,
            ],
            'recent' => [
                'withdrawals' => $recentWithdrawals,
                'users' => $recentUsers,
                'activities' => $activities,
            ],
        ];
    }

    private function isStaffNotAdmin(User $user): bool
    {
        return $user->hasRole('staff') && ! $user->hasRole('admin');
    }

    /**
     * @return Builder<User>
     */
    private function customersQuery(User $actor): Builder
    {
        $q = User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', 'user'))
            ->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['admin', 'staff']));

        if ($this->isStaffNotAdmin($actor)) {
            $q->where('created_by', (int) $actor->getKey());
        }

        return $q;
    }

    private function countStaffUsers(): int
    {
        return (int) User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', 'staff'))
            ->count();
    }

    /**
     * @return array{id: int, name: string, username: string, role_label: string|null}|null
     */
    private function serializeStaffActor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $roleLabel = match (true) {
            $user->hasRole('admin') => 'Quản trị',
            $user->hasRole('staff') => 'Nhân viên',
            default => null,
        };

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'role_label' => $roleLabel,
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolveRange(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $tz = (string) config('app.display_timezone', 'Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::now($tz);
        $period = in_array($period, ['today', '7d', '30d', 'month', 'custom'], true) ? $period : 'today';

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth()->startOfDay(), $now->endOfDay()],
            'custom' => $this->resolveCustomRange($now, $dateFrom, $dateTo, $tz),
            default => [$now->startOfDay(), $now->endOfDay()],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveCustomRange(CarbonImmutable $now, ?string $dateFrom, ?string $dateTo, string $tz): array
    {
        try {
            $from = $dateFrom
                ? CarbonImmutable::createFromFormat('Y-m-d', $dateFrom, $tz)
                : $now->subDays(6)->startOfDay();
        } catch (\Throwable) {
            $from = $now->subDays(6)->startOfDay();
        }

        try {
            $to = $dateTo
                ? CarbonImmutable::createFromFormat('Y-m-d', $dateTo, $tz)
                : $now;
        } catch (\Throwable) {
            $to = $now;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($to->subDays(365)->isAfter($from)) {
            $from = $to->subDays(365);
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function periodLabel(string $period, CarbonImmutable $start, CarbonImmutable $end, ?string $dateFrom, ?string $dateTo): string
    {
        return match ($period) {
            'today' => 'Hôm nay',
            '7d' => '7 ngày gần nhất',
            '30d' => '30 ngày gần nhất',
            'month' => 'Tháng này',
            'custom' => $start->isSameDay($end)
                ? 'Ngày '.$end->day.'/'.$end->month.'/'.$end->year
                : 'Từ '.$start->day.'/'.$start->month.' đến '.$end->day.'/'.$end->month,
            default => 'Hôm nay',
        };
    }

    /**
     * Tên cột thời gian lưu UTC (Eloquent) — trả về biểu thức SQL: ngày lịch Y-m-d theo
     * `config('app.display_timezone')`, trùng cách tính kỳ trên chart (vòng lặp theo múi VN).
     * Trước đây dùng DATE(created_at) nên theo ngày UTC → nạp 28/4 VN bị tính 27/4.
     */
    private function sqlLocalDateForUtcColumn(string $tableColumn): string
    {
        $driver = DB::getDriverName();
        $tz = (string) config('app.display_timezone', 'Asia/Ho_Chi_Minh');
        if (! preg_match('/^[A-Za-z0-9_\/+\-]+$/', $tz)) {
            $tz = 'Asia/Ho_Chi_Minh';
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $to = $this->mysqlOffsetStringForTimezone($tz);

            return "DATE(CONVERT_TZ({$tableColumn}, '+00:00', '{$to}'))";
        }

        if ($driver === 'sqlite') {
            $modifier = $this->sqliteHoursModifierForTimezone($tz);

            return "date({$tableColumn}, '{$modifier}')";
        }

        if ($driver === 'pgsql') {
            if (! preg_match('/^[A-Za-z0-9_\/+\-]+$/', $tz)) {
                $tz = 'Asia/Ho_Chi_Minh';
            }

            return "to_char((({$tableColumn} AT TIME ZONE 'UTC') AT TIME ZONE '{$tz}')::date, 'YYYY-MM-DD')";
        }

        return "DATE({$tableColumn})";
    }

    /**
     * Dạng +HH:MM:SS cho tham số thứ 3 của MySQL CONVERT_TZ (không phụ thuộc bảng time_zone).
     */
    private function mysqlOffsetStringForTimezone(string $timeZoneName): string
    {
        try {
            $utc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $offsetSeconds = (new \DateTimeZone($timeZoneName))->getOffset($utc);
            $sign = $offsetSeconds >= 0 ? '+' : '-';
            $abs = abs($offsetSeconds);
            $h = intdiv($abs, 3600);
            $m = intdiv($abs % 3600, 60);
            $s = $abs % 60;

            return sprintf('%s%02d:%02d:%02d', $sign, $h, $m, $s);
        } catch (\Throwable) {
            return '+07:00:00';
        }
    }

    /**
     * Bù giờ UTC → múi hiển thị khi dùng SQLite (test).
     * Ví dụ: '+7 hours' cho Asia/Ho_Chi_Minh.
     */
    private function sqliteHoursModifierForTimezone(string $timeZoneName): string
    {
        try {
            $utc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $offsetSeconds = (new \DateTimeZone($timeZoneName))->getOffset($utc);
            $hours = (int) round($offsetSeconds / 3600);
            if ($hours === 0) {
                return '+0 hours';
            }
            if ($hours > 0) {
                return "+{$hours} hours";
            }

            return (string) $hours.' hours';
        } catch (\Throwable) {
            return '+7 hours';
        }
    }

    /**
     * @param  Builder<Model>| \Illuminate\Database\Query\Builder  $userIdsSub
     */
    private function sumWalletInRange(
        $userIdsSub,
        CarbonImmutable $start,
        CarbonImmutable $end,
        WalletSource $source,
        WalletDirection $direction,
    ): int {
        return (int) (WalletTransaction::query()
            ->whereIn('user_id', $userIdsSub)
            ->where('source', $source)
            ->where('direction', $direction)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount_vnd') ?? 0);
    }

    /**
     * @param  Builder<Model>|\Illuminate\Database\Query\Builder  $userIdsSub
     */
    private function sumApprovedWithdrawalsInRange(
        $userIdsSub,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        return (int) (WithdrawalRequest::query()
            ->whereIn('user_id', $userIdsSub)
            ->where('status', WithdrawalStatus::Approved->value)
            ->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$start, $end])
            ->sum('amount_vnd') ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildChartSeries(
        User $actor,
        $customerIdsSub,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $wDay = $this->sqlLocalDateForUtcColumn('wallet_transactions.created_at');
        $eDay = $this->sqlLocalDateForUtcColumn('event_bets.created_at');
        $uDay = $this->sqlLocalDateForUtcColumn('users.created_at');

        $depositsByDay = $this->groupSumWalletByDay(
            $customerIdsSub,
            $start,
            $end,
            $wDay,
            WalletSource::AdminCredit,
            WalletDirection::Credit,
        );
        $withdrawalsByDay = $this->groupApprovedWithdrawalsByDay(
            $customerIdsSub,
            $start,
            $end,
        );
        $adminDebitByDay = $this->groupSumWalletByDay(
            $customerIdsSub,
            $start,
            $end,
            $wDay,
            WalletSource::AdminDebit,
            WalletDirection::Debit,
        );
        $commissionByDay = $this->groupSumWalletByDay(
            $customerIdsSub,
            $start,
            $end,
            $wDay,
            WalletSource::Commission,
            WalletDirection::Credit,
        );

        $newUsersByDay = $this->customersQuery($actor)
            ->whereBetween('created_at', [$start, $end])
            ->toBase()
            ->selectRaw("{$uDay} as d, COUNT(*) as c")
            ->groupByRaw($uDay)
            ->pluck('c', 'd')
            ->all();

        $betsByDay = EventBet::query()
            ->whereIn('user_id', $customerIdsSub)
            ->whereBetween('created_at', [$start, $end])
            ->toBase()
            ->selectRaw("{$eDay} as d, COUNT(*) as c")
            ->groupByRaw($eDay)
            ->pluck('c', 'd')
            ->all();

        $out = [];
        for ($c = $start; $c->lte($end); $c = $c->addDay()) {
            $k = $c->toDateString();
            $out[] = [
                'key' => $k,
                'label' => (string) $c->day.'/'.$c->month,
                'deposit_vnd' => (int) ($depositsByDay[$k] ?? 0),
                'withdrawal_vnd' => (int) ($withdrawalsByDay[$k] ?? 0),
                'admin_debit_vnd' => (int) ($adminDebitByDay[$k] ?? 0),
                'commission_vnd' => (int) ($commissionByDay[$k] ?? 0),
                'new_users' => (int) ($newUsersByDay[$k] ?? 0),
                'event_bets' => (int) ($betsByDay[$k] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int|string>
     */
    private function groupSumWalletByDay(
        $userIdsSub,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $dayExpr,
        WalletSource $source,
        WalletDirection $direction,
    ): array {
        return WalletTransaction::query()
            ->whereIn('user_id', $userIdsSub)
            ->where('source', $source)
            ->where('direction', $direction)
            ->whereBetween('created_at', [$start, $end])
            ->toBase()
            ->selectRaw("{$dayExpr} as d, COALESCE(SUM(amount_vnd),0) as t")
            ->groupByRaw($dayExpr)
            ->pluck('t', 'd')
            ->all();
    }

    /**
     * @return array<string, int|string>
     */
    private function groupApprovedWithdrawalsByDay(
        $userIdsSub,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $dExpr = $this->sqlLocalDateForUtcColumn('withdrawal_requests.processed_at');

        return WithdrawalRequest::query()
            ->whereIn('user_id', $userIdsSub)
            ->where('status', WithdrawalStatus::Approved->value)
            ->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$start, $end])
            ->toBase()
            ->selectRaw("{$dExpr} as d, COALESCE(SUM(amount_vnd),0) as t")
            ->groupByRaw($dExpr)
            ->pluck('t', 'd')
            ->all();
    }

    /**
     * @return Builder<ActivityLog>
     */
    private function recentActivitiesQuery(User $actor, $customerIdsSub, bool $isAdmin): Builder
    {
        if ($isAdmin) {
            return ActivityLog::query();
        }

        return ActivityLog::query()
            ->where(function ($q) use ($actor, $customerIdsSub) {
                $q->whereIn('target_user_id', $customerIdsSub)
                    ->orWhere('actor_id', (int) $actor->getKey());
            });
    }
}

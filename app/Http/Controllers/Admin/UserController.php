<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustUserBalanceRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ActivityLogger;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private WalletService $wallet) {}

    /**
     * Roles the current viewer is allowed to assign.
     *
     * @return list<string>
     */
    private function roleOptions(Request $request): array
    {
        $viewer = $request->user();

        if ($viewer?->hasRole('admin')) {
            return ['admin', 'staff', 'user'];
        }

        return ['user'];
    }

    private function isStaffOnly(?User $viewer): bool
    {
        return $viewer !== null
            && $viewer->hasRole('staff')
            && ! $viewer->hasRole('admin');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();
        $isStaffOnly = $this->isStaffOnly($viewer);

        $search = trim((string) $request->query('q', ''));
        $ipFilter = trim((string) $request->query('ip', ''));
        $statusFilter = (string) $request->query('status', '');
        $managerFilter = (int) $request->query('manager_id', 0);
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(5, min($perPage, 100));

        $users = User::query()
            ->with(['roles', 'creator:id,name,username'])
            ->withCount('eventBets')
            ->when($viewer !== null, fn ($query) => $query->whereKeyNot($viewer->getKey()))
            // This screen lists customers only; admin/staff are managed on the dedicated staff page.
            ->whereHas('roles', fn ($q) => $q->where('name', 'user'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff']))
            ->when($isStaffOnly, function ($query) use ($viewer) {
                $query->where('created_by', $viewer?->getKey());
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(username) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
                });
            })
            ->when($ipFilter !== '', function ($query) use ($ipFilter) {
                $like = '%'.$ipFilter.'%';
                $query->where('last_login_ip', 'like', $like);
            })
            ->when(in_array($statusFilter, ['active', 'locked'], true), function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->when(! $isStaffOnly && $managerFilter > 0, function ($query) use ($managerFilter) {
                $query->where('created_by', $managerFilter);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user) => $this->rowPayload($user, $request->user()));

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'ip' => $ipFilter,
                'status' => $statusFilter,
                'manager_id' => $managerFilter > 0 ? $managerFilter : null,
                'per_page' => $perPage,
            ],
            'statusOptions' => collect(UserStatus::cases())
                ->map(fn (UserStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'managerOptions' => $isStaffOnly ? [] : $this->managerOptions(),
        ]);
    }

    /**
     * Build the list of admin/staff candidates allowed as a "manager" (created_by).
     *
     * @return list<array{id: int, name: string, username: string}>
     */
    private function managerOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff']))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $u) => [
                'id' => (int) $u->getKey(),
                'name' => $u->name,
                'username' => $u->username,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve which user id should be stored as `created_by`.
     *
     * Only admins are allowed to override the manager. Other actors always
     * fall back to the provided default (typically the actor itself).
     */
    private function resolveManagerId(Request $request, mixed $requested, int $default): int
    {
        $viewer = $request->user();

        if ($viewer === null || ! $viewer->hasRole('admin')) {
            return $default;
        }

        $requestedId = is_numeric($requested) ? (int) $requested : 0;

        if ($requestedId <= 0) {
            return $default;
        }

        $isManager = User::query()
            ->whereKey($requestedId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff']))
            ->exists();

        return $isManager ? $requestedId : $default;
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        $viewer = $request->user();
        $isAdmin = $viewer?->hasRole('admin') === true;

        return Inertia::render('admin/users/Create', [
            'roleOptions' => $this->roleOptions($request),
            'managerOptions' => $isAdmin ? $this->managerOptions() : [],
            'defaultManagerId' => (int) ($viewer?->getKey() ?? 0),
            'canAssignManager' => $isAdmin,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        if (! in_array($role, $this->roleOptions($request), true)) {
            abort(403, 'Bạn không có quyền tạo tài khoản với vai trò này.');
        }

        $plainPassword = (string) ($data['password'] ?? '');
        $data['email'] = $this->generateUniqueEmail($data['username']);
        $data['created_by'] = $this->resolveManagerId(
            $request,
            $data['created_by'] ?? null,
            (int) ($request->user()?->getKey() ?? 0),
        );
        $data['password_hint'] = $plainPassword;

        $user = User::create($data);
        $user->assignRole($role);

        ActivityLogger::log(
            'user.created',
            (int) $user->getKey(),
            sprintf('Tạo tài khoản %s (%s)', $user->name, $user->username),
            ['role' => $role],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    private function generateUniqueEmail(string $username): string
    {
        $domain = 'sjcsukien.com';

        $base = Str::slug($username, '.') ?: 'user';
        $email = $base.'@'.$domain;

        $i = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = $base.$i.'@'.$domain;
            $i++;
        }

        return $email;
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $viewer = $request->user();
        $isAdmin = $viewer?->hasRole('admin') === true;

        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'balance_vnd' => (int) $user->balance_vnd,
                'role' => $user->roles->first()?->name ?? 'user',
                'bank_name' => $user->bank_name,
                'bank_account_number' => $user->bank_account_number,
                'bank_account_name' => $user->bank_account_name,
                'created_by' => $user->created_by !== null ? (int) $user->created_by : null,
            ],
            'roleOptions' => $this->roleOptions($request),
            'bankOptions' => $this->bankOptions(),
            'managerOptions' => $isAdmin ? $this->managerOptions() : [],
            'defaultManagerId' => (int) ($viewer?->getKey() ?? 0),
            'canAssignManager' => $isAdmin,
        ]);
    }

    public function deposit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $perPage = max(5, min((int) $request->integer('per_page', 20), 100));
        $filter = $this->normalizeWalletListFilter($request->query('filter'));

        if ($filter === 'freeze' && (int) ($user->frozen_vnd ?? 0) <= 1) {
            $filter = 'all';
        }

        $transactions = WalletTransaction::query()
            ->where('user_id', $user->getKey());
        $this->applyWalletListFilter($transactions, $filter);
        $transactions = $transactions
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (WalletTransaction $t) => [
                'id' => $t->id,
                'direction' => $t->direction->value,
                'source' => $t->source->value,
                'source_label' => $t->source->label(),
                'amount_vnd' => (int) $t->amount_vnd,
                'balance_after_vnd' => (int) $t->balance_after_vnd,
                'description' => $t->description,
                'created_at' => $t->created_at?->formatVn(),
            ]);

        $totals = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('direction, COALESCE(SUM(amount_vnd), 0) as sum_amount, COUNT(*) as total')
            ->groupBy('direction')
            ->get()
            ->keyBy('direction');

        $creditTotal = (int) ($totals[WalletDirection::Credit->value]->sum_amount ?? 0);
        $debitTotal = (int) ($totals[WalletDirection::Debit->value]->sum_amount ?? 0);

        return Inertia::render('admin/users/Deposit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'balance_vnd' => (int) $user->balance_vnd,
                'frozen_vnd' => (int) ($user->frozen_vnd ?? 0),
                'available_vnd' => $user->availableVnd(),
                'role' => $user->roles->first()?->name ?? 'user',
            ],
            'transactions' => $transactions,
            'filter' => $filter,
            'list_totals' => $this->computeDepositListTotals($user),
            'summary' => [
                'net_vnd' => $creditTotal - $debitTotal,
            ],
        ]);
    }

    public function adjustBalance(AdjustUserBalanceRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $operation = (string) $data['operation'];
        $amount = (int) $data['amount_vnd'];
        $note = $data['note'] ?? null;
        $note = is_string($note) && $note !== '' ? $note : null;
        $adminId = (int) ($request->user()?->getKey() ?? 0);

        if (in_array($operation, ['freeze', 'unfreeze'], true)) {
            $defaultNote = $operation === 'freeze'
                ? 'Lý do đóng băng: Sai thao tác'
                : 'Mở đóng băng';
            $verb = $operation === 'freeze' ? 'Đã đóng băng' : 'Đã mở đóng băng';
            $logAction = $operation === 'freeze' ? 'wallet.freeze' : 'wallet.unfreeze';
            $description = $note ?? $defaultNote;

            DB::transaction(function () use ($user, $amount, $operation, $description, $adminId) {
                /** @var User $locked */
                $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                if ($operation === 'freeze') {
                    $this->wallet->freeze($locked, $amount, $description, ['admin_id' => $adminId]);
                } else {
                    $this->wallet->unfreeze($locked, $amount, $description, ['admin_id' => $adminId]);
                }
            });

            ActivityLogger::log(
                $logAction,
                (int) $user->getKey(),
                sprintf('%s %s VNĐ', $verb, number_format($amount, 0, ',', '.')),
                ['amount_vnd' => $amount, 'note' => $description],
            );

            $message = sprintf('%s %s VNĐ.', $verb, number_format($amount, 0, ',', '.'));

            return back()->with('success', $message);
        }

        [$direction, $source, $defaultNote, $verb, $logAction] = match ($operation) {
            'credit' => [WalletDirection::Credit, WalletSource::AdminCredit, 'Nạp tiền thành công', 'Đã nạp', 'wallet.credit'],
            'debit' => [WalletDirection::Debit, WalletSource::AdminDebit, 'Rút tiền thành công', 'Đã trừ', 'wallet.debit'],
            'commission' => [WalletDirection::Credit, WalletSource::Commission, 'Thưởng hoa hồng', 'Đã thưởng hoa hồng', 'wallet.commission'],
            default => throw new \InvalidArgumentException('Invalid balance operation'),
        };

        DB::transaction(function () use ($user, $amount, $direction, $source, $note, $defaultNote, $adminId) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $this->wallet->apply(
                $locked,
                $direction,
                $source,
                $amount,
                $note ?? $defaultNote,
                ['admin_id' => $adminId],
            );
        });

        $resolvedNote = $note ?? $defaultNote;

        ActivityLogger::log(
            $logAction,
            (int) $user->getKey(),
            sprintf('%s %s VNĐ', $verb, number_format($amount, 0, ',', '.')),
            ['amount_vnd' => $amount, 'note' => $resolvedNote],
        );

        $message = sprintf('%s %s VNĐ.', $verb, number_format($amount, 0, ',', '.'));

        return back()->with('success', $message);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        if (! in_array($role, $this->roleOptions($request), true)) {
            abort(403, 'Bạn không có quyền gán vai trò này.');
        }

        $changedBank = ($data['bank_name'] ?? null) !== $user->bank_name
            || ($data['bank_account_number'] ?? null) !== $user->bank_account_number
            || ($data['bank_account_name'] ?? null) !== $user->bank_account_name;

        if (! empty($data['password'] ?? '')) {
            $data['password_hint'] = $data['password'];
        } else {
            $data = Arr::except($data, ['password']);
        }

        $data['created_by'] = $this->resolveManagerId(
            $request,
            $data['created_by'] ?? null,
            $user->created_by !== null ? (int) $user->created_by : (int) ($request->user()?->getKey() ?? 0),
        );

        $user->fill($data);
        $user->save();
        $user->syncRoles([$role]);

        ActivityLogger::log(
            'user.updated',
            (int) $user->getKey(),
            sprintf('Cập nhật %s', $user->name),
            ['role' => $role],
        );

        if ($changedBank) {
            ActivityLogger::log(
                'bank.updated',
                (int) $user->getKey(),
                'Cập nhật thông tin ngân hàng',
                Arr::only($data, ['bank_name', 'bank_account_number', 'bank_account_name']),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $name = $user->name;
        $username = $user->username;
        $id = (int) $user->getKey();

        // Log BEFORE delete so the target_user_id FK still resolves; once the
        // user row is gone we cannot insert a row referencing it. Snapshot
        // identifying info into meta for future reference.
        ActivityLogger::log(
            'user.deleted',
            $id,
            sprintf('Xóa tài khoản %s', $name),
            [
                'deleted_user_id' => $id,
                'deleted_username' => $username,
                'deleted_name' => $name,
            ],
        );

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Toggle a user's lock status (admin/staff only, scoped per policy).
     */
    public function toggleLock(Request $request, User $user): RedirectResponse
    {
        $this->authorize('lock', $user);

        $reason = $request->input('reason');
        $reason = is_string($reason) ? mb_substr(trim($reason), 0, 255) : null;
        $actorId = (int) ($request->user()?->getKey() ?? 0);

        if ($user->isLocked()) {
            $user->status = UserStatus::Active;
            $user->locked_at = null;
            $user->locked_by = null;
            $user->lock_reason = null;
            $user->save();

            ActivityLogger::log(
                'user.unlocked',
                (int) $user->getKey(),
                sprintf('Mở khóa %s', $user->name),
            );

            return back()->with('success', 'Đã mở khóa tài khoản.');
        }

        $user->status = UserStatus::Locked;
        $user->locked_at = now();
        $user->locked_by = $actorId ?: null;
        $user->lock_reason = $reason;
        $user->save();

        ActivityLogger::log(
            'user.locked',
            (int) $user->getKey(),
            sprintf('Khóa %s', $user->name).($reason ? ' · '.$reason : ''),
            ['reason' => $reason],
        );

        return back()->with('success', 'Đã khóa tài khoản.');
    }

    /**
     * Return the decrypted plain password for admin/staff (eye-icon reveal).
     */
    public function password(Request $request, User $user): JsonResponse
    {
        $this->authorize('viewPassword', $user);

        return response()->json([
            'password' => $user->password_hint,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(User $user, ?User $viewer): array
    {
        $role = $user->roles->first();
        $status = $user->status instanceof UserStatus ? $user->status : UserStatus::Active;

        $canView = $viewer !== null && $viewer->can('viewPassword', $user);
        $canLock = $viewer !== null && $viewer->can('lock', $user);
        $canDelete = $viewer !== null && $viewer->can('delete', $user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'balance_vnd' => (int) $user->balance_vnd,
            'role' => $role?->name ?? 'user',
            'event_count' => (int) ($user->event_bets_count ?? 0),
            'status' => $status->value,
            'status_label' => $status->label(),
            'last_login_at' => $user->last_login_at?->formatVn(),
            'last_login_ip' => $user->last_login_ip,
            'created_at' => $user->created_at?->formatVn(),
            'creator' => $user->creator === null ? null : [
                'id' => (int) $user->creator->getKey(),
                'name' => $user->creator->name,
                'username' => $user->creator->username,
            ],
            'can_view_password' => $canView,
            'can_lock' => $canLock,
            'can_delete' => $canDelete,
        ];
    }

    /**
     * @return array{
     *   adminCreditVnd: int,
     *   refundVnd: int,
     *   betPlaceVnd: int,
     *   commissionVnd: int,
     *   outDebitVnd: int,
     *   freezeVnd: int,
     *   adminCreditCount: int,
     *   refundCount: int,
     *   betPlaceCount: int,
     *   commissionCount: int,
     *   outDebitCount: int,
     *   freezeCount: int
     * }
     */
    private function computeDepositListTotals(User $user): array
    {
        $c = WalletSource::AdminCredit->value;
        $bc = WalletSource::BetCancel->value;
        $er = WalletSource::EventRefund->value;
        $bp = WalletSource::BetPlace->value;
        $cm = WalletSource::Commission->value;
        $ad = WalletSource::AdminDebit->value;
        $w = WalletSource::Withdrawal->value;
        $fr = WalletSource::AdminFreeze->value;
        $ur = WalletSource::AdminUnfreeze->value;
        $d = WalletDirection::Debit->value;

        $row = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('
                coalesce(sum(case when source = ? then amount_vnd else 0 end), 0) as admin_credit_vnd,
                count(case when source = ? then 1 else null end) as admin_credit_count,
                coalesce(sum(case when source in (?, ?) then amount_vnd else 0 end), 0) as refund_vnd,
                count(case when source in (?, ?) then 1 else null end) as refund_count,
                coalesce(sum(case when source = ? then amount_vnd else 0 end), 0) as bet_place_vnd,
                count(case when source = ? then 1 else null end) as bet_place_count,
                coalesce(sum(case when source = ? then amount_vnd else 0 end), 0) as commission_vnd,
                count(case when source = ? then 1 else null end) as commission_count,
                coalesce(sum(case when direction = ? and source in (?, ?) then amount_vnd else 0 end), 0) as out_debit_vnd,
                count(case when direction = ? and source in (?, ?) then 1 else null end) as out_debit_count,
                count(case when source in (?, ?) then 1 else null end) as freeze_count
            ', [
                $c, $c,
                $bc, $er, $bc, $er,
                $bp, $bp,
                $cm, $cm,
                $d, $ad, $w, $d, $ad, $w,
                $fr, $ur,
            ])
            ->first();

        return [
            'adminCreditVnd' => (int) ($row->admin_credit_vnd ?? 0),
            'refundVnd' => (int) ($row->refund_vnd ?? 0),
            'betPlaceVnd' => (int) ($row->bet_place_vnd ?? 0),
            'commissionVnd' => (int) ($row->commission_vnd ?? 0),
            'outDebitVnd' => (int) ($row->out_debit_vnd ?? 0),
            'freezeVnd' => (int) ($user->frozen_vnd ?? 0),
            'adminCreditCount' => (int) ($row->admin_credit_count ?? 0),
            'refundCount' => (int) ($row->refund_count ?? 0),
            'betPlaceCount' => (int) ($row->bet_place_count ?? 0),
            'commissionCount' => (int) ($row->commission_count ?? 0),
            'outDebitCount' => (int) ($row->out_debit_count ?? 0),
            'freezeCount' => (int) ($row->freeze_count ?? 0),
        ];
    }

    /**
     * @param  Builder<WalletTransaction>  $query
     */
    private function applyWalletListFilter(Builder $query, string $filter): void
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

    private function normalizeWalletListFilter(mixed $raw): string
    {
        $value = is_string($raw) ? $raw : 'all';

        return in_array(
            $value,
            ['all', 'credit', 'refund', 'debit', 'commission', 'bet_place', 'freeze'],
            true,
        ) ? $value : 'all';
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
}

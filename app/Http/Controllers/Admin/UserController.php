<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustUserBalanceRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(5, min($perPage, 100));

        $users = User::query()
            ->with(['roles', 'creator:id,name,username'])
            ->withCount('eventBets')
            ->when($isStaffOnly, function ($query) use ($viewer) {
                $query->where('created_by', $viewer?->getKey())
                    ->whereDoesntHave('roles', function ($q) {
                        $q->whereIn('name', ['admin', 'staff']);
                    });
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'ilike', $like)
                        ->orWhere('username', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (User $user) {
                $role = $user->roles->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'balance_vnd' => (int) $user->balance_vnd,
                    'role' => $role?->name ?? 'user',
                    'event_count' => (int) ($user->event_bets_count ?? 0),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'creator' => $user->creator === null ? null : [
                        'id' => (int) $user->creator->getKey(),
                        'name' => $user->creator->name,
                        'username' => $user->creator->username,
                    ],
                ];
            });

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => ['q' => $search, 'per_page' => $perPage],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/Create', [
            'roleOptions' => $this->roleOptions($request),
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

        $data['email'] = $this->generateUniqueEmail($data['username']);
        $data['created_by'] = $request->user()?->getKey();

        $user = User::create($data);
        $user->assignRole($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    private function generateUniqueEmail(string $username): string
    {
        $domain = 'sjcsukien.com';

        for ($i = 0; $i < 20; $i++) {
            $local = Str::lower(fake()->unique()->userName());
            $email = $local.'@'.$domain;

            if (! User::query()->where('email', $email)->exists()) {
                return $email;
            }
        }

        $fallback = Str::slug($username, '.') ?: 'user';

        do {
            $email = $fallback.Str::lower(Str::random(4)).'@'.$domain;
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'balance_vnd' => (int) $user->balance_vnd,
                'role' => $user->roles->first()?->name ?? 'user',
            ],
            'roleOptions' => $this->roleOptions($request),
        ]);
    }

    public function deposit(User $user): Response
    {
        $this->authorize('update', $user);

        $transactions = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WalletTransaction $t) => [
                'id' => $t->id,
                'direction' => $t->direction->value,
                'source' => $t->source->value,
                'source_label' => $t->source->label(),
                'amount_vnd' => (int) $t->amount_vnd,
                'balance_after_vnd' => (int) $t->balance_after_vnd,
                'description' => $t->description,
                'created_at' => $t->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $totals = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('direction, COALESCE(SUM(amount_vnd), 0) as sum_amount, COUNT(*) as total')
            ->groupBy('direction')
            ->get()
            ->keyBy('direction');

        $commission = WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->where('source', WalletSource::Commission->value)
            ->selectRaw('COALESCE(SUM(amount_vnd), 0) as sum_amount, COUNT(*) as total')
            ->first();

        return Inertia::render('admin/users/Deposit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'balance_vnd' => (int) $user->balance_vnd,
                'role' => $user->roles->first()?->name ?? 'user',
            ],
            'transactions' => $transactions,
            'summary' => [
                'credit_total' => (int) ($totals[WalletDirection::Credit->value]->sum_amount ?? 0),
                'credit_count' => (int) ($totals[WalletDirection::Credit->value]->total ?? 0),
                'debit_total' => (int) ($totals[WalletDirection::Debit->value]->sum_amount ?? 0),
                'debit_count' => (int) ($totals[WalletDirection::Debit->value]->total ?? 0),
                'commission_total' => (int) ($commission->sum_amount ?? 0),
                'commission_count' => (int) ($commission->total ?? 0),
            ],
        ]);
    }

    public function adjustBalance(AdjustUserBalanceRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $operation = $data['operation'];
        $amount = (int) $data['amount_vnd'];
        $note = $data['note'] ?? null;
        $adminId = (int) ($request->user()?->getKey() ?? 0);

        [$direction, $source, $defaultNote, $verb] = match ($operation) {
            'credit' => [WalletDirection::Credit, WalletSource::AdminCredit, 'Nạp tiền thành công', 'Đã nạp'],
            'debit' => [WalletDirection::Debit, WalletSource::AdminDebit, 'Rút tiền thành công', 'Đã trừ'],
            'commission' => [WalletDirection::Credit, WalletSource::Commission, 'Thưởng hoa hồng', 'Đã thưởng hoa hồng'],
        };

        DB::transaction(function () use ($user, $amount, $direction, $source, $note, $defaultNote, $adminId) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $this->wallet->apply(
                $locked,
                $direction,
                $source,
                $amount,
                $note ?: $defaultNote,
                ['admin_id' => $adminId],
            );
        });

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

        if (empty($data['password'] ?? '')) {
            $data = Arr::except($data, ['password']);
        }

        $user->fill($data);
        $user->save();
        $user->syncRoles([$role]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }
}

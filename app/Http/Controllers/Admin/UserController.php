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
     * @return list<string>
     */
    private function roleOptions(): array
    {
        return ['admin', 'user'];
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $role = $user->roles->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'balance_vnd' => (int) $user->balance_vnd,
                    'role' => $role?->name ?? 'user',
                ];
            });

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/Create', [
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $data['email'] = $this->generateUniqueEmail($data['username']);

        $user = User::create($data);
        $user->assignRole($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    private function generateUniqueEmail(string $username): string
    {
        $base = Str::slug($username, '.') ?: 'user';

        do {
            $email = $base.'.'.Str::lower(Str::random(8)).'@example.com';
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }

    public function edit(User $user): Response
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
            'roleOptions' => $this->roleOptions(),
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
            ],
        ]);
    }

    public function adjustBalance(AdjustUserBalanceRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $isCredit = $data['operation'] === 'credit';
        $amount = (int) $data['amount_vnd'];
        $note = $data['note'] ?? null;
        $adminId = (int) ($request->user()?->getKey() ?? 0);

        DB::transaction(function () use ($user, $amount, $isCredit, $note, $adminId) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $this->wallet->apply(
                $locked,
                $isCredit ? WalletDirection::Credit : WalletDirection::Debit,
                $isCredit ? WalletSource::AdminCredit : WalletSource::AdminDebit,
                $amount,
                $note ?: ($isCredit ? 'Admin nạp số dư' : 'Admin trừ số dư'),
                ['admin_id' => $adminId],
            );
        });

        $message = $isCredit
            ? sprintf('Đã nạp %s VNĐ vào số dư.', number_format($amount, 0, ',', '.'))
            : sprintf('Đã trừ %s VNĐ khỏi số dư.', number_format($amount, 0, ',', '.'));

        return back()->with('success', $message);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

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

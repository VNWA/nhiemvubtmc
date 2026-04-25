<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $perPage = max(5, min((int) $request->integer('per_page', 15), 100));

        $staff = User::query()
            ->with(['roles'])
            ->withCount(['managedUsers', 'eventBets'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'staff'))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(username) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            })
            ->when(in_array($statusFilter, ['active', 'locked'], true), function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user) => $this->payload($user));

        return Inertia::render('admin/staff/Index', [
            'staff' => $staff,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'per_page' => $perPage,
            ],
            'statusOptions' => collect(UserStatus::cases())
                ->map(fn (UserStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('admin/staff/Create');
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plainPassword = (string) $data['password'];

        $data['email'] = $this->generateUniqueEmail($data['username']);
        $data['created_by'] = $request->user()?->getKey();
        $data['password_hint'] = $plainPassword;

        $user = User::create($data);
        $user->assignRole('staff');

        ActivityLogger::log(
            'user.created',
            (int) $user->getKey(),
            sprintf('Tạo nhân viên %s (%s)', $user->name, $user->username),
            ['role' => 'staff'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã tạo tài khoản nhân viên.']);

        return to_route('admin.staff.index');
    }

    public function edit(Request $request, User $staff): Response
    {
        $this->ensureAdmin($request);
        $this->ensureStaff($staff);

        return Inertia::render('admin/staff/Edit', [
            'staff' => $this->payload($staff, includePassword: true),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);

        $data = $request->validated();

        if (! empty($data['password'] ?? '')) {
            $data['password_hint'] = $data['password'];
        } else {
            $data = Arr::except($data, ['password']);
        }

        $staff->fill($data);
        $staff->save();

        ActivityLogger::log(
            'user.updated',
            (int) $staff->getKey(),
            sprintf('Cập nhật nhân viên %s', $staff->name),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã cập nhật nhân viên.']);

        return to_route('admin.staff.index');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $this->ensureAdmin($request);
        $this->ensureStaff($staff);

        $name = $staff->name;
        $id = (int) $staff->getKey();
        $staff->delete();

        ActivityLogger::log(
            'user.deleted',
            $id,
            sprintf('Xóa nhân viên %s', $name),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã xóa tài khoản nhân viên.']);

        return to_route('admin.staff.index');
    }

    public function toggleLock(Request $request, User $staff): RedirectResponse
    {
        $this->ensureAdmin($request);
        $this->ensureStaff($staff);

        $reason = $request->input('reason');
        $reason = is_string($reason) ? mb_substr(trim($reason), 0, 255) : null;
        $actorId = (int) ($request->user()?->getKey() ?? 0);

        if ($staff->isLocked()) {
            $staff->status = UserStatus::Active;
            $staff->locked_at = null;
            $staff->locked_by = null;
            $staff->lock_reason = null;
            $staff->save();

            ActivityLogger::log('user.unlocked', (int) $staff->getKey(), sprintf('Mở khóa nhân viên %s', $staff->name));

            return back()->with('success', 'Đã mở khóa nhân viên.');
        }

        $staff->status = UserStatus::Locked;
        $staff->locked_at = now();
        $staff->locked_by = $actorId ?: null;
        $staff->lock_reason = $reason;
        $staff->save();

        ActivityLogger::log(
            'user.locked',
            (int) $staff->getKey(),
            sprintf('Khóa nhân viên %s', $staff->name).($reason ? ' · '.$reason : ''),
            ['reason' => $reason],
        );

        return back()->with('success', 'Đã khóa nhân viên.');
    }

    public function password(Request $request, User $staff): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->ensureStaff($staff);

        return response()->json(['password' => $staff->password_hint]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin') ?? false, 403);
    }

    private function ensureStaff(User $staff): void
    {
        abort_unless($staff->hasRole('staff'), 404);
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

        $fallback = Str::slug($username, '.') ?: 'staff';

        do {
            $email = $fallback.Str::lower(Str::random(4)).'@'.$domain;
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, bool $includePassword = false): array
    {
        $status = $user->status instanceof UserStatus ? $user->status : UserStatus::Active;

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $status->value,
            'status_label' => $status->label(),
            'managed_users_count' => (int) ($user->managed_users_count ?? 0),
            'event_bets_count' => (int) ($user->event_bets_count ?? 0),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'created_at' => $user->created_at?->toIso8601String(),
            'password' => $includePassword ? $user->password_hint : null,
        ];
    }
}

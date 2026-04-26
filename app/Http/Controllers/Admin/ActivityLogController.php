<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('admin') ?? false, 403);

        $action = (string) $request->query('action', '');
        $actor = (int) $request->query('actor_id', 0);
        $target = (int) $request->query('target_user_id', 0);
        $search = trim((string) $request->query('q', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = max(10, min((int) $request->integer('per_page', 25), 100));

        $logs = ActivityLog::query()
            ->with(['actor:id,name,username', 'target:id,name,username'])
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($actor > 0, fn ($q) => $q->where('actor_id', $actor))
            ->when($target > 0, fn ($q) => $q->where('target_user_id', $target))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(description) LIKE ?', [$like])
                        ->orWhereHas('actor', function ($u) use ($like) {
                            $u->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(username) LIKE ?', [$like]);
                        })
                        ->orWhereHas('target', function ($u) use ($like) {
                            $u->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(username) LIKE ?', [$like]);
                        });
                });
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom) {
                try {
                    $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                } catch (\Throwable) {
                }
            })
            ->when($dateTo !== '', function ($query) use ($dateTo) {
                try {
                    $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                } catch (\Throwable) {
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => (int) $log->getKey(),
                'action' => $log->action,
                'action_label' => $log->actionLabel(),
                'description' => $log->description,
                'meta' => $log->meta,
                'ip' => $log->ip,
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

        $actionOptions = collect([
            'user.created' => 'Tạo người dùng',
            'user.updated' => 'Cập nhật người dùng',
            'user.deleted' => 'Xóa người dùng',
            'user.locked' => 'Khóa tài khoản',
            'user.unlocked' => 'Mở khóa tài khoản',
            'user.login' => 'Đăng nhập',
            'wallet.credit' => 'Nạp tiền',
            'wallet.debit' => 'Trừ tiền',
            'wallet.commission' => 'Thưởng hoa hồng',
            'bank.updated' => 'Cập nhật ngân hàng',
        ])->map(fn (string $label, string $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return Inertia::render('admin/activities/Index', [
            'logs' => $logs,
            'filters' => [
                'action' => $action,
                'actor_id' => $actor > 0 ? $actor : null,
                'target_user_id' => $target > 0 ? $target : null,
                'q' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'actionOptions' => $actionOptions,
        ]);
    }
}

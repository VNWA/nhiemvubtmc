<?php

namespace App\Listeners;

use App\Jobs\UpdateUserLastAccessJob;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class UpdateUserOnLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $ip = Request::ip();
        UpdateUserLastAccessJob::dispatch(
            userId: (int) $user->getKey(),
            ip: is_string($ip) ? $ip : null,
        );

        ActivityLogger::log(
            action: 'user.login',
            targetUserId: (int) $user->getKey(),
            description: 'Đăng nhập từ IP '.($ip !== null && $ip !== '' ? $ip : 'không xác định'),
            actorId: (int) $user->getKey(),
        );
    }
}

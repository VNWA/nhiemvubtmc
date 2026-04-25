<?php

namespace App\Listeners;

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

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->saveQuietly();

        ActivityLogger::log(
            action: 'user.login',
            targetUserId: (int) $user->getKey(),
            description: 'Đăng nhập từ IP '.($ip ?? 'unknown'),
            actorId: (int) $user->getKey(),
        );
    }
}

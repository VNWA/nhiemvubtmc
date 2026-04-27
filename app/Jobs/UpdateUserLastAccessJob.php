<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateUserLastAccessJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public ?string $ip,
    ) {}

    public function handle(): void
    {
        $user = User::query()->whereKey($this->userId)->first();

        if ($user === null) {
            return;
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->ip,
        ])->saveQuietly();
    }
}

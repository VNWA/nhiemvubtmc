<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordActivityLogJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $action,
        public ?int $targetUserId,
        public ?string $description,
        public ?array $meta,
        public ?int $actorId,
        public ?string $ip,
    ) {}

    public function handle(): void
    {
        ActivityLog::create([
            'actor_id' => $this->actorId,
            'target_user_id' => $this->targetUserId,
            'action' => $this->action,
            'description' => $this->description,
            'meta' => $this->meta,
            'ip' => $this->ip,
        ]);
    }
}

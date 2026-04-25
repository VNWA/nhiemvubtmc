<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Record an activity entry.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function log(
        string $action,
        ?int $targetUserId = null,
        ?string $description = null,
        ?array $meta = null,
        ?int $actorId = null,
    ): ActivityLog {
        if ($actorId === null) {
            /** @var User|null $actor */
            $actor = Auth::user();
            $actorId = $actor?->getKey();
        }

        return ActivityLog::create([
            'actor_id' => $actorId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'description' => $description,
            'meta' => $meta,
            'ip' => Request::ip(),
        ]);
    }
}

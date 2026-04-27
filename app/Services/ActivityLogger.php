<?php

namespace App\Services;

use App\Jobs\RecordActivityLogJob;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Enqueue an activity entry (async with a non-sync queue driver).
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function log(
        string $action,
        ?int $targetUserId = null,
        ?string $description = null,
        ?array $meta = null,
        ?int $actorId = null,
    ): void {
        if ($actorId === null) {
            /** @var User|null $actor */
            $actor = Auth::user();
            $actorId = $actor?->getKey();
        }

        RecordActivityLogJob::dispatch(
            action: $action,
            targetUserId: $targetUserId,
            description: $description,
            meta: $meta,
            actorId: $actorId,
            ip: Request::ip(),
        );
    }
}

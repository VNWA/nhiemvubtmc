<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartEventRoundRequest;
use App\Models\EventRoom;
use App\Models\EventRound;
use App\Services\EventRoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class EventRoundController extends Controller
{
    public function start(StartEventRoundRequest $request, EventRoom $eventRoom, EventRoundService $rounds): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }
        $name = $request->validated('name');
        $durationSeconds = $request->filled('duration_seconds')
            ? (int) $request->validated('duration_seconds')
            : null;
        $autoRollover = (bool) $request->boolean('auto_rollover');

        try {
            $rounds->startRound($eventRoom, $user, $name, $durationSeconds, $autoRollover);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã bắt đầu phiên mới.');
    }

    public function end(EventRoom $eventRoom, EventRound $round, EventRoundService $rounds): RedirectResponse
    {
        if ((int) $round->event_room_id !== (int) $eventRoom->getKey()) {
            abort(404);
        }
        $user = request()->user();
        if ($user === null) {
            abort(403);
        }

        try {
            $rounds->endRound($round, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        // Pressing "Kết thúc phiên" must also break the auto-rollover loop —
        // otherwise the queued AutoEndExpiredRoundJob would keep spawning new
        // rounds. Reload to avoid a stale model overwriting concurrent edits.
        $eventRoom->refresh();
        if ($eventRoom->auto_rollover_seconds !== null) {
            $eventRoom->forceFill(['auto_rollover_seconds' => null])->save();
        }

        return back()->with('success', 'Đã kết thúc phiên.');
    }
}

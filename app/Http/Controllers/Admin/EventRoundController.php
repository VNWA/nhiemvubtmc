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
        $preset = (int) $request->validated('preset_option_id');
        $name = $request->validated('name');
        $durationSeconds = $request->filled('duration_seconds')
            ? (int) $request->validated('duration_seconds')
            : null;

        try {
            $rounds->startRound($eventRoom, $preset, $user, $name, $durationSeconds);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã bắt đầu kỳ mới.');
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

        return back()->with('success', 'Đã kết thúc kỳ.');
    }
}

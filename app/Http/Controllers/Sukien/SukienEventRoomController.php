<?php

namespace App\Http\Controllers\Sukien;

use App\Enums\EventRoundStatus;
use App\Http\Controllers\Controller;
use App\Models\EventBet;
use App\Models\EventRoom;
use App\Models\EventRound;
use App\Services\EventRoundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SukienEventRoomController extends Controller
{
    private const ROUNDS_PER_PAGE = 10;

    public function __construct(private EventRoundService $rounds) {}

    public function index(): Response
    {
        $rooms = EventRoom::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'avatar_path'])
            ->map(fn (EventRoom $r) => [
                'id' => (int) $r->getKey(),
                'name' => $r->name,
                'slug' => $r->slug,
                'avatar_url' => $r->avatar_url,
            ]);

        return Inertia::render('sukien/Index', [
            'rooms' => $rooms,
        ]);
    }

    public function show(Request $request, string $slug): RedirectResponse|Response
    {
        $room = EventRoom::query()->where('slug', $slug)->first();

        if ($room === null) {
            return redirect()
                ->route('sukien.index')
                ->with('error', 'Không tìm thấy sự kiện.');
        }

        $room->load(['options' => fn ($q) => $q->orderBy('sort_order')]);
        $openRound = $room->openRound();
        if ($openRound) {
            // Defensive close if the timer expired while the queue worker was offline.
            if ($openRound->auto_end_at !== null && $openRound->auto_end_at->isPast()) {
                $this->rounds->autoEndRound($openRound);
                $openRound = null;
            }
        }

        $user = $request->user();
        $userBet = null;
        if ($user !== null && $openRound !== null) {
            /** @var EventBet|null $bet */
            $bet = EventBet::query()
                ->where('event_round_id', $openRound->getKey())
                ->where('user_id', $user->getKey())
                ->first();
            if ($bet !== null) {
                $optionIds = collect($bet->selected_option_ids ?? [])
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();
                $labels = $bet->selectedOptionLabels(
                    $room->options->keyBy('id')
                );
                $userBet = [
                    'id' => (int) $bet->getKey(),
                    'option_ids' => $optionIds,
                    'option_labels' => $labels,
                    'amount_vnd' => (int) $bet->amount_vnd,
                ];
            }
        }

        $closedQuery = EventRound::query()
            ->where('event_room_id', $room->getKey())
            ->where('status', EventRoundStatus::Closed);

        $recentRoundsTotal = (clone $closedQuery)->count();
        $recentRounds = (clone $closedQuery)
            ->orderByDesc('round_number')
            ->limit(self::ROUNDS_PER_PAGE)
            ->get()
            ->map(fn (EventRound $r) => $this->formatRound($r));

        $options = $room->options->map(fn ($o) => [
            'id' => (int) $o->getKey(),
            'label' => $o->label,
            'bg_color' => $o->bg_color,
            'text_color' => $o->text_color,
        ]);

        $isAdmin = $user?->hasRole('admin') ?? false;

        $betsStats = null;
        if ($isAdmin && $openRound !== null) {
            $betsStats = $this->betsStatsForRound($openRound);
        }

        return Inertia::render('sukien/Show', [
            'eventRoom' => [
                'id' => (int) $room->getKey(),
                'name' => $room->name,
                'slug' => $room->slug,
                'avatar_url' => $room->avatar_url,
                'is_active' => $room->is_active,
                'viewer_offset' => (int) $room->viewer_offset,
            ],
            'options' => $options,
            'openRound' => $openRound === null ? null : [
                'id' => (int) $openRound->getKey(),
                'round_number' => (int) $openRound->round_number,
                'name' => $openRound->name,
                'started_at' => $openRound->started_at?->toIso8601String(),
                'auto_end_at' => $openRound->auto_end_at?->toIso8601String(),
                'duration_seconds' => $openRound->duration_seconds === null
                    ? null
                    : (int) $openRound->duration_seconds,
            ],
            'recentRounds' => $recentRounds,
            'recentRoundsTotal' => $recentRoundsTotal,
            'recentRoundsPerPage' => self::ROUNDS_PER_PAGE,
            'userBet' => $userBet,
            'betsStats' => $betsStats,
            'isAdmin' => $isAdmin,
            'userBalanceVnd' => $user === null ? 0 : (int) $user->balance_vnd,
        ]);
    }

    public function roundsHistory(Request $request, string $slug): JsonResponse
    {
        $room = EventRoom::query()->where('slug', $slug)->firstOrFail();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = self::ROUNDS_PER_PAGE;

        $base = EventRound::query()
            ->where('event_room_id', $room->getKey())
            ->where('status', EventRoundStatus::Closed);

        $total = (clone $base)->count();

        $items = (clone $base)
            ->orderByDesc('round_number')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $items->map(fn (EventRound $r) => $this->formatRound($r))->values(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hasMore' => $page * $perPage < $total,
        ]);
    }

    /**
     * @return array{id: int, round_number: int, name: string, ended_at: ?string}
     */
    private function formatRound(EventRound $round): array
    {
        return [
            'id' => (int) $round->getKey(),
            'round_number' => (int) $round->round_number,
            'name' => $round->name,
            'ended_at' => $round->ended_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{betsCount: int, totalAmountVnd: int}
     */
    private function betsStatsForRound(EventRound $round): array
    {
        $agg = EventBet::query()
            ->where('event_round_id', $round->getKey())
            ->selectRaw('count(*) as c, coalesce(sum(amount_vnd),0) as t')
            ->first();

        return [
            'betsCount' => (int) ($agg->c ?? 0),
            'totalAmountVnd' => (int) ($agg->t ?? 0),
        ];
    }
}

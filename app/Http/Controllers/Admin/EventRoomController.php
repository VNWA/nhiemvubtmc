<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventRoundStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRoomRequest;
use App\Http\Requests\Admin\UpdateEventRoomRequest;
use App\Models\EventBet;
use App\Models\EventRoom;
use App\Models\EventRoomOption;
use App\Models\EventRound;
use App\Models\User;
use App\Services\EventRoundService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventRoomController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(): Response
    {
        $rooms = EventRoom::query()
            ->withCount('options')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EventRoom $r) => [
                'id' => (int) $r->getKey(),
                'name' => $r->name,
                'slug' => $r->slug,
                'avatar_url' => $r->avatar_url,
                'is_active' => $r->is_active,
                'options_count' => (int) $r->options_count,
            ]);

        return Inertia::render('admin/sukien-rooms/Index', [
            'rooms' => $rooms,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sukien-rooms/Create');
    }

    public function store(StoreEventRoomRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = $this->makeUniqueSlug($data['name']);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('event-rooms', 'public');
        }

        $room = EventRoom::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'avatar_path' => $avatarPath,
            'is_active' => (string) $request->input('is_active', '1') === '1',
        ]);

        foreach (array_values($data['options']) as $order => $opt) {
            EventRoomOption::query()->create([
                'event_room_id' => $room->getKey(),
                'label' => $opt['label'],
                'bg_color' => $this->normalizeHex($opt['bg_color'] ?? null, '#c62828'),
                'text_color' => $this->normalizeHex($opt['text_color'] ?? null, '#ffffff'),
                'sort_order' => $order,
            ]);
        }

        return redirect()
            ->route('admin.sukien-rooms.index')
            ->with('success', 'Đã tạo phòng sự kiện.');
    }

    public function edit(EventRoom $eventRoom): Response
    {
        return Inertia::render('admin/sukien-rooms/Edit', [
            'room' => [
                'id' => (int) $eventRoom->getKey(),
                'name' => $eventRoom->name,
                'slug' => $eventRoom->slug,
                'avatar_url' => $eventRoom->avatar_url,
                'is_active' => $eventRoom->is_active,
            ],
        ]);
    }

    public function manage(EventRoom $eventRoom, EventRoundService $rounds): Response
    {
        $eventRoom->load(['options' => fn ($q) => $q->orderBy('sort_order')]);

        $openRound = $eventRoom->openRound();
        if ($openRound !== null && $openRound->auto_end_at !== null && $openRound->auto_end_at->isPast()) {
            $rounds->autoEndRound($openRound);
            $openRound = null;
        }
        if ($openRound !== null) {
            $openRound->load('presetOption');
        }

        $perOption = [];
        $betsCount = 0;
        $totalAmountVnd = 0;
        if ($openRound !== null) {
            $rows = EventBet::query()
                ->where('event_round_id', $openRound->getKey())
                ->selectRaw('option_id, count(*) as c, coalesce(sum(amount_vnd),0) as t')
                ->groupBy('option_id')
                ->get();
            foreach ($rows as $row) {
                $perOption[] = [
                    'optionId' => (int) $row->option_id,
                    'betsCount' => (int) $row->c,
                    'totalAmountVnd' => (int) $row->t,
                ];
                $betsCount += (int) $row->c;
                $totalAmountVnd += (int) $row->t;
            }
        }

        $recentRounds = EventRound::query()
            ->where('event_room_id', $eventRoom->getKey())
            ->where('status', EventRoundStatus::Closed)
            ->with('presetOption')
            ->orderByDesc('round_number')
            ->limit(20)
            ->get()
            ->map(fn (EventRound $r) => [
                'id' => (int) $r->getKey(),
                'round_number' => (int) $r->round_number,
                'name' => $r->name,
                'ended_at' => $r->ended_at?->toIso8601String(),
                'preset' => [
                    'label' => $r->presetOption->label,
                    'bg_color' => $r->presetOption->bg_color,
                    'text_color' => $r->presetOption->text_color,
                ],
            ]);

        return Inertia::render('admin/sukien-rooms/Manage', [
            'eventRoom' => [
                'id' => (int) $eventRoom->getKey(),
                'name' => $eventRoom->name,
                'slug' => $eventRoom->slug,
                'avatar_url' => $eventRoom->avatar_url,
                'is_active' => $eventRoom->is_active,
            ],
            'options' => $eventRoom->options->map(fn (EventRoomOption $o) => [
                'id' => (int) $o->getKey(),
                'label' => $o->label,
                'bg_color' => $o->bg_color,
                'text_color' => $o->text_color,
            ])->values(),
            'openRound' => $openRound === null ? null : [
                'id' => (int) $openRound->getKey(),
                'round_number' => (int) $openRound->round_number,
                'name' => $openRound->name,
                'started_at' => $openRound->started_at?->toIso8601String(),
                'auto_end_at' => $openRound->auto_end_at?->toIso8601String(),
                'duration_seconds' => $openRound->duration_seconds === null
                    ? null
                    : (int) $openRound->duration_seconds,
                'preset' => $openRound->presetOption === null ? null : [
                    'id' => (int) $openRound->presetOption->getKey(),
                    'label' => $openRound->presetOption->label,
                    'bg_color' => $openRound->presetOption->bg_color,
                    'text_color' => $openRound->presetOption->text_color,
                ],
            ],
            'betsStats' => [
                'betsCount' => $betsCount,
                'totalAmountVnd' => $totalAmountVnd,
                'perOption' => $perOption,
            ],
            'recentRounds' => $recentRounds,
            'durationLimits' => [
                'minSeconds' => EventRoundService::MIN_DURATION_SECONDS,
                'maxSeconds' => EventRoundService::MAX_DURATION_SECONDS,
            ],
        ]);
    }

    public function update(UpdateEventRoomRequest $request, EventRoom $eventRoom): RedirectResponse
    {
        $data = $request->validated();

        $attributes = [
            'name' => $data['name'],
            'is_active' => (string) $request->input('is_active', '0') === '1',
        ];

        if ($request->hasFile('avatar')) {
            if ($eventRoom->avatar_path) {
                Storage::disk('public')->delete($eventRoom->avatar_path);
            }
            $attributes['avatar_path'] = $request->file('avatar')->store('event-rooms', 'public');
        } elseif ((string) $request->input('remove_avatar', '0') === '1' && $eventRoom->avatar_path) {
            Storage::disk('public')->delete($eventRoom->avatar_path);
            $attributes['avatar_path'] = null;
        }

        $eventRoom->update($attributes);

        return redirect()
            ->route('admin.sukien-rooms.index')
            ->with('success', 'Đã cập nhật phòng.');
    }

    public function destroy(EventRoom $eventRoom): RedirectResponse
    {
        DB::transaction(function () use ($eventRoom) {
            $roomId = $eventRoom->getKey();

            /** @var \Illuminate\Support\Collection<int, EventRound> $openRounds */
            $openRounds = EventRound::query()
                ->where('event_room_id', $roomId)
                ->where('status', EventRoundStatus::Open)
                ->lockForUpdate()
                ->get();

            foreach ($openRounds as $round) {
                /** @var \Illuminate\Support\Collection<int, EventBet> $bets */
                $bets = EventBet::query()
                    ->where('event_round_id', $round->getKey())
                    ->with('option')
                    ->lockForUpdate()
                    ->get();

                foreach ($bets as $bet) {
                    /** @var User $lockedUser */
                    $lockedUser = User::query()
                        ->whereKey($bet->user_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $amount = (int) $bet->amount_vnd;
                    $optionLabel = $bet->option?->label;
                    $betId = (int) $bet->getKey();

                    $bet->delete();

                    $this->wallet->apply(
                        $lockedUser,
                        WalletDirection::Credit,
                        WalletSource::BetCancel,
                        $amount,
                        'Hoàn cược do xóa phòng "'.$eventRoom->name.'" (kỳ #'.$round->round_number.')',
                        [
                            'event_room_id' => (int) $roomId,
                            'event_room_name' => $eventRoom->name,
                            'event_round_id' => (int) $round->getKey(),
                            'round_number' => (int) $round->round_number,
                            'option_label' => $optionLabel,
                            'bet_id' => $betId,
                            'reason' => 'room_deleted',
                        ],
                    );
                }
            }

            EventBet::query()
                ->whereIn('event_round_id', EventRound::query()
                    ->where('event_room_id', $roomId)
                    ->select('id'))
                ->delete();

            EventRound::query()->where('event_room_id', $roomId)->delete();
            EventRoomOption::query()->where('event_room_id', $roomId)->delete();

            if ($eventRoom->avatar_path) {
                Storage::disk('public')->delete($eventRoom->avatar_path);
            }

            $eventRoom->delete();
        });

        return redirect()
            ->route('admin.sukien-rooms.index')
            ->with('success', 'Đã xóa phòng sự kiện và hoàn tiền các cược đang mở.');
    }

    /**
     * Build a unique, URL-safe slug from the room name; falls back to a random
     * slug when the name doesn't contain slug-safe characters.
     */
    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'sukien-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $i = 0;
        while (EventRoom::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function normalizeHex(?string $value, string $fallback): string
    {
        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        return preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $value) === 1
            ? Str::lower($value)
            : $fallback;
    }
}

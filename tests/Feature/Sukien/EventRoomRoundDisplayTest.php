<?php

namespace Tests\Feature\Sukien;

use App\Enums\EventRoundStatus;
use App\Models\EventRoom;
use App\Models\EventRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventRoomRoundDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_refreshes_open_round_after_lazy_close_and_rollover(): void
    {
        $user = User::factory()->create();
        $room = EventRoom::query()->create([
            'name' => 'Phòng A',
            'slug' => 'phong-a',
            'is_active' => true,
            'round_session' => 1,
            'auto_rollover_seconds' => 60,
        ]);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 1,
            'round_number' => 3,
            'name' => 'Phiên #3',
            'status' => EventRoundStatus::Open,
            'duration_seconds' => 60,
            'started_at' => now()->subMinutes(5),
            'auto_end_at' => now()->subMinute(),
            'ended_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('sukien.show', ['slug' => 'phong-a']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sukien/Show')
                ->where('openRound.round_number', 4)
            );
    }

    public function test_closed_rounds_list_only_includes_current_round_session(): void
    {
        $user = User::factory()->create();
        $room = EventRoom::query()->create([
            'name' => 'Phòng B',
            'slug' => 'phong-b',
            'is_active' => true,
            'round_session' => 2,
        ]);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 1,
            'round_number' => 1544,
            'name' => 'Phiên cũ',
            'status' => EventRoundStatus::Closed,
            'duration_seconds' => 60,
            'started_at' => now()->subDays(2),
            'auto_end_at' => null,
            'ended_at' => now()->subDays(2),
        ]);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 2,
            'round_number' => 1,
            'name' => 'Phiên #1',
            'status' => EventRoundStatus::Closed,
            'duration_seconds' => 60,
            'started_at' => now()->subHour(),
            'auto_end_at' => null,
            'ended_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('sukien.show', ['slug' => 'phong-b']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sukien/Show')
                ->where('recentRoundsTotal', 1)
                ->has('recentRounds', 1)
                ->where('recentRounds.0.round_number', 1)
            );
    }

    public function test_rounds_history_json_is_scoped_to_current_session(): void
    {
        $user = User::factory()->create();
        $room = EventRoom::query()->create([
            'name' => 'Phòng C',
            'slug' => 'phong-c',
            'is_active' => true,
            'round_session' => 2,
        ]);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 1,
            'round_number' => 99,
            'name' => 'Cũ',
            'status' => EventRoundStatus::Closed,
            'duration_seconds' => 60,
            'started_at' => now()->subDay(),
            'auto_end_at' => null,
            'ended_at' => now()->subDay(),
        ]);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 2,
            'round_number' => 2,
            'name' => 'Phiên #2',
            'status' => EventRoundStatus::Closed,
            'duration_seconds' => 60,
            'started_at' => now()->subHour(),
            'auto_end_at' => null,
            'ended_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->getJson(route('sukien.rounds.history', ['slug' => 'phong-c', 'page' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.round_number', 2);
    }
}

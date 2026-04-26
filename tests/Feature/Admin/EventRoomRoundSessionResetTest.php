<?php

namespace Tests\Feature\Admin;

use App\Enums\EventRoundStatus;
use App\Models\EventRoom;
use App\Models\EventRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventRoomRoundSessionResetTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_reset_round_session_increments_ky_and_next_starts_at_number_one(): void
    {
        $admin = $this->makeAdmin();
        $room = EventRoom::query()->create([
            'name' => 'P1',
            'slug' => 'p1',
            'is_active' => true,
            'round_session' => 1,
        ]);
        $this->assertSame(1, (int) $room->fresh()->round_session);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 1,
            'round_number' => 22,
            'name' => 'Phiên #22',
            'status' => EventRoundStatus::Closed,
            'duration_seconds' => 60,
            'started_at' => now()->subHour(),
            'auto_end_at' => null,
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.sukien-rooms.manage', $room))
            ->post(route('admin.sukien-rooms.rounds.reset-session', $room))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $room->refresh();
        $this->assertSame(2, (int) $room->round_session);

        $this->actingAs($admin)
            ->post(route('admin.sukien-rooms.rounds.start', $room), [
                'name' => '',
                'duration_seconds' => 60,
                'auto_rollover' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $new = EventRound::query()->where('event_room_id', $room->id)->where('status', EventRoundStatus::Open)->first();
        $this->assertNotNull($new);
        $this->assertSame(2, (int) $new->round_session);
        $this->assertSame(1, (int) $new->round_number);
        $this->assertSame('Phiên #1', $new->name);
    }

    public function test_cannot_reset_while_a_round_is_open(): void
    {
        $admin = $this->makeAdmin();
        $room = EventRoom::query()->create([
            'name' => 'P2',
            'slug' => 'p2',
            'is_active' => true,
            'round_session' => 1,
        ]);

        EventRound::query()->create([
            'event_room_id' => $room->id,
            'round_session' => 1,
            'round_number' => 1,
            'name' => 'Mở',
            'status' => EventRoundStatus::Open,
            'duration_seconds' => 60,
            'started_at' => now(),
            'auto_end_at' => null,
            'ended_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sukien-rooms.rounds.reset-session', $room))
            ->assertSessionHasErrors('round');
    }
}

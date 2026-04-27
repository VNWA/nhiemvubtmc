<?php

namespace Tests\Feature\Admin;

use App\Enums\EventBetStatus;
use App\Enums\EventRoundStatus;
use App\Models\EventBet;
use App\Models\EventRoom;
use App\Models\EventRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserEventsPageTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: EventRoom, 1: list<EventRound>}
     */
    private function makeRoomWithRounds(int $count): array
    {
        $room = EventRoom::query()->create([
            'name' => 'Phòng Test Events',
            'slug' => 'phong-test-events-'.mb_substr(uniqid(), -6),
            'is_active' => true,
            'round_session' => 1,
        ]);

        $rounds = [];
        for ($n = 1; $n <= $count; $n++) {
            $rounds[] = EventRound::query()->create([
                'event_room_id' => $room->id,
                'round_session' => 1,
                'round_number' => $n,
                'name' => 'Phiên #'.$n,
                'status' => EventRoundStatus::Closed,
                'duration_seconds' => 60,
                'started_at' => now()->subHours($n),
                'auto_end_at' => null,
                'ended_at' => now()->subHours($n),
            ]);
        }

        return [$room, $rounds];
    }

    public function test_admin_gets_paginated_event_bets_with_filters_inertia(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create();
        $customer->assignRole('user');

        [$room, $rounds] = $this->makeRoomWithRounds(6);
        foreach ($rounds as $round) {
            EventBet::query()->create([
                'user_id' => $customer->id,
                'event_round_id' => $round->id,
                'selected_option_ids' => [],
                'amount_vnd' => 100_000,
                'status' => EventBetStatus::Pending,
                'refund_vnd' => 0,
                'commission_vnd' => 0,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.users.events.index', [
                'user' => $customer->getKey(),
                'per_page' => 5,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Events')
                ->where('bets.last_page', 2)
                ->where('bets.per_page', 5)
                ->has('bets.data', 5)
                ->where('filters.q', '')
                ->where('filters.per_page', 5)
            );

        $this->actingAs($admin)
            ->get(route('admin.users.events.index', [
                'user' => $customer->getKey(),
                'q' => (string) $room->name,
                'per_page' => 20,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bets.total', 6)
            );
    }
}

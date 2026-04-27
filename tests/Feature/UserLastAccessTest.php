<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLastAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_browsing_does_not_require_last_access_write(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_each_non_ignored_request_triggers_fresh_truy_cap_cuoi(): void
    {
        $stale = now()->subDay();
        $user = User::factory()->create([
            'last_login_at' => $stale,
            'last_login_ip' => '10.0.0.1',
        ]);

        $this->actingAs($user);

        $this->get(route('home'));
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertTrue($user->last_login_at->isAfter($stale));
        $t1 = $user->last_login_at->getTimestamp();
        $this->assertNotEmpty($user->last_login_ip);

        $this->travel(2)->seconds();
        $this->get(route('home'));
        $user->refresh();
        $this->assertGreaterThan($t1, $user->last_login_at->getTimestamp());
    }

    public function test_ignored_routes_do_not_bump_truy_cap_cuoi(): void
    {
        $stale = now()->subDay();
        $user = User::factory()->create([
            'last_login_at' => $stale,
            'last_login_ip' => '10.0.0.1',
        ]);

        $lastAccessBefore = (int) $user->fresh()->last_login_at?->getTimestamp();
        $this->assertNotSame(0, $lastAccessBefore);

        $this->actingAs($user);

        $this->get(route('account.wallet.data'))->assertOk();
        $user->refresh();
        $this->assertSame($lastAccessBefore, (int) $user->last_login_at?->getTimestamp());

        $this->get(route('home'));
        $user->refresh();
        $this->assertGreaterThan($lastAccessBefore, (int) $user->last_login_at?->getTimestamp());
    }
}

<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function createUserRole(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_authenticated_user_can_view_account_profile_page(): void
    {
        $this->createUserRole();
        $user = User::factory()->create(['phone' => '0909123456']);
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('account.profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('account/Profile')
            ->where('profile.phone', '0909123456')
        );
    }

    public function test_client_cannot_patch_account_profile_from_public_route(): void
    {
        $this->createUserRole();
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->patch('/tai-khoan/ho-so', ['phone' => '0910000000'])
            ->assertStatus(405);
    }
}

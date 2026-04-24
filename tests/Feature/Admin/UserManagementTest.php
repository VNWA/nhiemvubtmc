<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_guests_cannot_view_admin_users_index(): void
    {
        $this->createRoles();

        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_admin_users_index(): void
    {
        $this->createRoles();
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_view_users_index(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_user_without_email(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'username' => 'newuser',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'user',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('username', 'newuser')->first();
        $this->assertNotNull($created);
        $this->assertNotEmpty($created->email);
        $this->assertStringContainsString('@example.com', $created->email);
        $this->assertTrue($created->hasRole('user'));
    }

    public function test_username_is_stripped_of_whitespace_when_creating(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Spaced',
            'username' => '  spa ced 1 ',
            'password' => 'pw',
            'password_confirmation' => 'pw',
            'role' => 'user',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['username' => 'spaced1']);
    }

    public function test_admin_cannot_create_user_with_duplicate_username(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        User::factory()->create(['username' => 'existing']);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Dup User',
                'username' => 'existing',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'user',
            ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin));

        $response->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\EventRoom;
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
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
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

    public function test_admin_can_search_users_case_insensitively(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        User::factory()->create(['name' => 'Nguyen Van A', 'username' => 'nguyena']);
        User::factory()->create(['name' => 'Tran Thi B', 'username' => 'tranb']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'NGUYEN']));

        $response->assertOk();
    }

    public function test_admin_can_filter_users_by_last_login_ip(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $a = User::factory()->create(['last_login_ip' => '10.0.0.5']);
        $a->assignRole('user');
        $b = User::factory()->create(['last_login_ip' => '192.168.1.1']);
        $b->assignRole('user');

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['ip' => '10.0.0']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $a->id)
        );
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
        $this->assertStringContainsString('@', $created->email);
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

    public function test_staff_creating_user_forces_self_as_manager(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Khách Của Staff',
            'username' => 'khachstaff',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'user',
            'created_by' => $admin->id,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('username', 'khachstaff')->firstOrFail();
        $this->assertSame($staff->id, (int) $created->created_by);
    }

    public function test_admin_can_assign_manager_when_creating_user(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Khách Giao Staff',
            'username' => 'kgiao',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'user',
            'created_by' => $staff->id,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('username', 'kgiao')->firstOrFail();
        $this->assertSame($staff->id, (int) $created->created_by);
    }

    public function test_admin_can_delete_user_and_activity_log_is_recorded(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create(['name' => 'Bob', 'username' => 'bob']);
        $target->assignRole('user');

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'user.deleted',
        ]);
    }

    public function test_staff_cannot_delete_customer_even_if_created_by_them(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $customer = User::factory()->create(['created_by' => $staff->id]);
        $customer->assignRole('user');

        $response = $this->actingAs($staff)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $customer));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_admin_deposit_page_returns_paginated_wallet_transactions(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create();
        $customer->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users.deposit', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data')
                ->where('transactions.total', 0)
                ->where('transactions.current_page', 1)
                ->where('filter', 'all')
                ->has('list_totals')
            );
    }

    public function test_admin_deposit_page_accepts_filter_query(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create();
        $customer->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users.deposit', ['user' => $customer, 'filter' => 'commission']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filter', 'commission'));
    }

    public function test_admin_sukien_rooms_index_returns_paginated_rooms(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        EventRoom::query()->create([
            'name' => 'Test Room',
            'slug' => 'test-room-'.uniqid(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sukien-rooms.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rooms.data', 1)
                ->where('rooms.total', 1)
            );
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_cannot_view_admin_dashboard(): void
    {
        $this->createRoles();
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_view_admin_dashboard_with_payload(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => '7d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('scope.is_admin', true)
                ->has('chart_series')
                ->has('quick')
                ->has('quick.period_admin_debit_vnd')
                ->has('recent'));
    }

    public function test_staff_can_view_admin_dashboard_with_payload(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('scope.is_staff_only', true)
                ->has('chart_series'));
    }
}

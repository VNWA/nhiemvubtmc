<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffClearTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
    }

    public function test_admin_can_clear_two_factor_for_staff(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->forceFill([
            'two_factor_secret' => encrypt('s'),
            'two_factor_recovery_codes' => encrypt(json_encode(['a'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->actingAs($admin)->post(route('admin.staff.two-factor.clear', $staff));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $staff->refresh();
        $this->assertNull($staff->two_factor_secret);
        $this->assertNull($staff->two_factor_recovery_codes);
        $this->assertNull($staff->two_factor_confirmed_at);
    }

    public function test_non_admin_cannot_clear_staff_two_factor(): void
    {
        $this->createRoles();
        $otherStaff = User::factory()->create();
        $otherStaff->assignRole('staff');
        $target = User::factory()->create();
        $target->assignRole('staff');
        $target->forceFill([
            'two_factor_secret' => encrypt('s'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($otherStaff)
            ->post(route('admin.staff.two-factor.clear', $target))
            ->assertForbidden();
    }
}

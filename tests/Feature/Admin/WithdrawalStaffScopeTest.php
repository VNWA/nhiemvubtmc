<?php

namespace Tests\Feature\Admin;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WithdrawalStaffScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    private function makePendingWithdrawal(User $customer): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'user_id' => $customer->getKey(),
            'amount_vnd' => 100_000,
            'bank_name' => 'VCB',
            'bank_account_number' => '123456',
            'bank_account_name' => 'Test',
            'note' => null,
            'status' => WithdrawalStatus::Pending,
        ]);
    }

    public function test_staff_sees_only_withdrawals_for_users_they_manage(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $managed = User::factory()->create(['created_by' => $staff->getKey()]);
        $managed->assignRole('user');

        $other = User::factory()->create(['created_by' => null]);
        $other->assignRole('user');

        $w1 = $this->makePendingWithdrawal($managed);
        $w2 = $this->makePendingWithdrawal($other);

        $response = $this->actingAs($staff)->get(route('admin.withdrawals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.id', $w1->getKey())
        );
    }

    public function test_staff_summary_counts_only_managed_withdrawals(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $managed = User::factory()->create(['created_by' => $staff->getKey()]);
        $managed->assignRole('user');
        $other = User::factory()->create(['created_by' => null]);
        $other->assignRole('user');

        $this->makePendingWithdrawal($managed);
        $this->makePendingWithdrawal($other);

        $response = $this->actingAs($staff)->get(route('admin.withdrawals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.pending_count', 1)
        );
    }

    public function test_staff_cannot_approve_withdrawal_for_unmanaged_user(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $other = User::factory()->create(['created_by' => null]);
        $other->assignRole('user');
        $withdrawal = $this->makePendingWithdrawal($other);

        $this->actingAs($staff)
            ->post(route('admin.withdrawals.approve', $withdrawal), ['admin_note' => null])
            ->assertForbidden();
    }

    public function test_staff_can_approve_withdrawal_for_managed_user(): void
    {
        $this->createRoles();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $managed = User::factory()->create([
            'created_by' => $staff->getKey(),
            'balance_vnd' => 500_000,
        ]);
        $managed->assignRole('user');

        $withdrawal = $this->makePendingWithdrawal($managed);

        $this->actingAs($staff)
            ->post(route('admin.withdrawals.approve', $withdrawal), ['admin_note' => 'ok'])
            ->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame(WithdrawalStatus::Approved, $withdrawal->status);
    }

    public function test_admin_can_approve_any_pending_withdrawal(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $customer = User::factory()->create(['created_by' => null, 'balance_vnd' => 500_000]);
        $customer->assignRole('user');
        $withdrawal = $this->makePendingWithdrawal($customer);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $withdrawal), [])
            ->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame(WithdrawalStatus::Approved, $withdrawal->status);
    }
}

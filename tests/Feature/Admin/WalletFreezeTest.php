<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WalletFreezeTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_admin_freeze_reduces_only_available_not_total_balance(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create(['balance_vnd' => 500_000, 'frozen_vnd' => 0]);
        $customer->assignRole('user');

        $this->actingAs($admin)->from(route('admin.users.deposit', $customer))->post(
            route('admin.users.balance.adjust', $customer),
            [
                'operation' => 'freeze',
                'amount_vnd' => 400_000,
                'note' => null,
            ],
        )->assertSessionHasNoErrors()->assertRedirect();

        $customer->refresh();
        $this->assertSame(500_000, (int) $customer->balance_vnd);
        $this->assertSame(400_000, (int) $customer->frozen_vnd);
        $this->assertSame(100_000, $customer->availableVnd());

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $customer->getKey(),
            'source' => 'admin_freeze',
            'amount_vnd' => 400_000,
            'balance_after_vnd' => 500_000,
        ]);
    }

    public function test_freeze_uses_default_note_when_note_empty(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create(['balance_vnd' => 200_000, 'frozen_vnd' => 0]);
        $customer->assignRole('user');

        $this->actingAs($admin)->post(route('admin.users.balance.adjust', $customer), [
            'operation' => 'freeze',
            'amount_vnd' => 100_000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $customer->getKey(),
            'source' => 'admin_freeze',
            'description' => 'Lý do đóng băng: Sai thao tác',
        ]);
    }

    public function test_admin_cannot_freeze_more_than_available(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create(['balance_vnd' => 500_000, 'frozen_vnd' => 0]);
        $customer->assignRole('user');

        $this->actingAs($admin)->from(route('admin.users.deposit', $customer))->post(
            route('admin.users.balance.adjust', $customer),
            [
                'operation' => 'freeze',
                'amount_vnd' => 600_000,
            ],
        )->assertSessionHasErrors('amount_vnd');
    }

    public function test_admin_unfreeze_restores_spendable_balance(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create(['balance_vnd' => 500_000, 'frozen_vnd' => 200_000]);
        $customer->assignRole('user');

        $this->actingAs($admin)->from(route('admin.users.deposit', $customer))->post(
            route('admin.users.balance.adjust', $customer),
            [
                'operation' => 'unfreeze',
                'amount_vnd' => 150_000,
            ],
        )->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame(500_000, (int) $customer->balance_vnd);
        $this->assertSame(50_000, (int) $customer->frozen_vnd);
        $this->assertSame(450_000, $customer->availableVnd());

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $customer->getKey(),
            'source' => 'admin_unfreeze',
        ]);
    }

    public function test_admin_cannot_debit_more_than_current_balance(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create(['balance_vnd' => 100_000, 'frozen_vnd' => 0]);
        $customer->assignRole('user');

        $this->actingAs($admin)->from(route('admin.users.deposit', $customer))->post(
            route('admin.users.balance.adjust', $customer),
            [
                'operation' => 'debit',
                'amount_vnd' => 150_000,
            ],
        )->assertSessionHasErrors('amount_vnd');
    }

    public function test_debit_clamps_frozen_when_total_drops(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create(['balance_vnd' => 500_000, 'frozen_vnd' => 400_000]);
        $customer->assignRole('user');

        $this->actingAs($admin)->from(route('admin.users.deposit', $customer))->post(
            route('admin.users.balance.adjust', $customer),
            [
                'operation' => 'debit',
                'amount_vnd' => 300_000,
            ],
        )->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame(200_000, (int) $customer->balance_vnd);
        $this->assertSame(200_000, (int) $customer->frozen_vnd);
    }
}

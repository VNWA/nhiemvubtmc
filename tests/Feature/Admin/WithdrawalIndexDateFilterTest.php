<?php

namespace Tests\Feature\Admin;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WithdrawalIndexDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_date_range_uses_display_timezone_for_created_at_filter(): void
    {
        $this->createRoles();
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create();
        $customer->assignRole('user');

        $w = WithdrawalRequest::query()->create([
            'user_id' => $customer->getKey(),
            'amount_vnd' => 50_000,
            'bank_name' => 'VCB',
            'bank_account_number' => '001',
            'bank_account_name' => 'A',
            'note' => null,
            'status' => WithdrawalStatus::Pending->value,
            'admin_note' => null,
            'processed_by' => null,
            'processed_at' => null,
        ]);
        $w->created_at = CarbonImmutable::parse('2026-05-03 01:19:27', 'Asia/Ho_Chi_Minh');
        $w->saveQuietly();

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', [
                'date_from' => '2026-05-03',
                'date_to' => '2026-05-03',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/withdrawals/Index')
                ->where('display_timezone', 'Asia/Ho_Chi_Minh')
                ->where('items.data.0.id', (int) $w->getKey()));
    }
}

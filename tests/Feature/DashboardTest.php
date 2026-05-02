<?php

namespace Tests\Feature;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
                ->has('period.display_timezone')
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

    public function test_dashboard_recent_lists_include_staff_actor_payload(): void
    {
        $this->createRoles();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $staff = User::factory()->create(['username' => 'staff_processor']);
        $staff->assignRole('staff');
        $customer = User::factory()->create();
        $customer->assignRole('user');
        $customerStaffCreated = User::factory()->create([
            'username' => 'cust_staff',
            'created_by' => $staff->getKey(),
        ]);
        $customerStaffCreated->assignRole('user');

        WithdrawalRequest::query()->create([
            'user_id' => $customer->getKey(),
            'amount_vnd' => 100_000,
            'bank_name' => 'VCB',
            'bank_account_number' => '001',
            'bank_account_name' => 'Test',
            'note' => null,
            'status' => WithdrawalStatus::Approved->value,
            'admin_note' => null,
            'processed_by' => $staff->getKey(),
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent.withdrawals.0.processor.username', 'staff_processor')
                ->where('recent.withdrawals.0.processor.role_label', 'Nhân viên')
                ->where('recent.users.0.creator.username', 'staff_processor')
                ->where('recent.users.0.creator.role_label', 'Nhân viên'));
    }

    public function test_dashboard_custom_day_buckets_utc_instant_into_display_timezone_day(): void
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

        $at = CarbonImmutable::parse('2026-04-27 17:30:00', 'UTC');
        $tx = new WalletTransaction([
            'user_id' => $customer->getKey(),
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::AdminCredit,
            'amount_vnd' => 77_000,
            'balance_after_vnd' => 77_000,
            'description' => 'Midnight bucket test',
            'meta' => null,
        ]);
        $tx->created_at = $at;
        $tx->updated_at = $at;
        $tx->save();

        $this->actingAs($admin)
            ->get(route('admin.dashboard', [
                'period' => 'custom',
                'date_from' => '2026-04-28',
                'date_to' => '2026-04-28',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('chart_series.0.key', '2026-04-28')
                ->where('chart_series.0.deposit_vnd', 77_000));
    }

    public function test_overview_new_customers_today_includes_signups_after_midnight_display_timezone(): void
    {
        $this->createRoles();
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        Carbon::setTestNow(CarbonImmutable::parse('2026-05-03 12:00:00', 'Asia/Ho_Chi_Minh'));

        try {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $customer = User::factory()->create();
            $customer->assignRole('user');
            $customer->created_at = CarbonImmutable::parse('2026-05-03 00:43:45', 'Asia/Ho_Chi_Minh');
            $customer->saveQuietly();

            $this->actingAs($admin)
                ->get(route('admin.dashboard', ['period' => 'today']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('overview.new_customers_in_period', 1));
        } finally {
            Carbon::setTestNow();
        }
    }
}

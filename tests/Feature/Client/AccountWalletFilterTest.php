<?php

namespace Tests\Feature\Client;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountWalletFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_credit_filter_shows_only_admin_top_ups(): void
    {
        $user = User::factory()->create();

        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::AdminCredit,
            'amount_vnd' => 50_000,
            'balance_after_vnd' => 50_000,
            'description' => null,
        ]);
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::EventRefund,
            'amount_vnd' => 10_000,
            'balance_after_vnd' => 60_000,
            'description' => null,
        ]);

        $this->actingAs($user)
            ->get(route('account.wallet', ['filter' => 'credit']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/Wallet')
                ->has('transactions', 1)
                ->where('transactions.0.source', 'admin_credit')
            );
    }

    public function test_wallet_refund_filter_shows_bet_cancel_and_event_refund(): void
    {
        $user = User::factory()->create();

        foreach ([WalletSource::BetCancel, WalletSource::EventRefund] as $src) {
            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'direction' => WalletDirection::Credit,
                'source' => $src,
                'amount_vnd' => 5_000,
                'balance_after_vnd' => 5_000,
                'description' => null,
            ]);
        }
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::AdminCredit,
            'amount_vnd' => 100_000,
            'balance_after_vnd' => 105_000,
            'description' => null,
        ]);

        $this->actingAs($user)
            ->get(route('account.wallet', ['filter' => 'refund']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 2)
                ->where('filter', 'refund')
            );
    }

    public function test_wallet_freeze_filter_shows_only_freeze_and_unfreeze(): void
    {
        $user = User::factory()->create();

        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Debit,
            'source' => WalletSource::AdminFreeze,
            'amount_vnd' => 50_000,
            'balance_after_vnd' => 500_000,
            'description' => 'Lý do đóng băng: Sai thao tác',
        ]);
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::AdminUnfreeze,
            'amount_vnd' => 10_000,
            'balance_after_vnd' => 500_000,
            'description' => 'Mở đóng băng',
        ]);
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Debit,
            'source' => WalletSource::AdminDebit,
            'amount_vnd' => 5_000,
            'balance_after_vnd' => 495_000,
            'description' => null,
        ]);

        $this->actingAs($user)
            ->get(route('account.wallet', ['filter' => 'freeze']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/Wallet')
                ->has('transactions', 2)
                ->where('filter', 'freeze')
            );
    }

    public function test_wallet_orders_event_cluster_from_top_commission_refund_bet_reading_bottom_up_story(): void
    {
        $user = User::factory()->create();
        $betKey = 4242;

        $bet = WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Debit,
            'source' => WalletSource::BetPlace,
            'amount_vnd' => 1_000,
            'balance_after_vnd' => 99_000,
            'description' => 'Tham gia',
            'meta' => ['bet_id' => $betKey],
        ]);
        $bet->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::Commission,
            'amount_vnd' => 50,
            'balance_after_vnd' => 99_150,
            'description' => 'Hoa hồng',
            'meta' => ['event_bet_id' => $betKey],
        ])->forceFill(['updated_at' => now()])->saveQuietly();

        $refund = WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::EventRefund,
            'amount_vnd' => 100,
            'balance_after_vnd' => 99_100,
            'description' => 'Hoàn trả',
            'meta' => ['event_bet_id' => $betKey],
        ]);
        $refund->forceFill(['updated_at' => now()->subMinute()])->saveQuietly();

        $this->actingAs($user)
            ->get(route('account.wallet'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/Wallet')
                ->has('transactions', 3)
                ->where('transactions.0.source', 'commission')
                ->where('transactions.1.source', 'event_refund')
                ->where('transactions.2.source', 'bet_place')
            );
    }
}

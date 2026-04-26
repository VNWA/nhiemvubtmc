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
}

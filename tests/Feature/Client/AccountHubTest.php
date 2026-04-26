<?php

namespace Tests\Feature\Client;

use App\Enums\WalletDirection;
use App\Enums\WalletSource;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_show_includes_commission_total_aligned_with_non_commission_credits(): void
    {
        $user = User::factory()->create();

        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::AdminCredit,
            'amount_vnd' => 100_000,
            'balance_after_vnd' => 100_000,
            'description' => null,
        ]);
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'direction' => WalletDirection::Credit,
            'source' => WalletSource::Commission,
            'amount_vnd' => 25_000,
            'balance_after_vnd' => 125_000,
            'description' => null,
        ]);

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/Show')
                ->where('totals.totalCreditVnd', 100_000)
                ->where('totals.totalCommissionVnd', 25_000)
            );
    }
}

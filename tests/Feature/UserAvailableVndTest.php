<?php

namespace Tests\Feature;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAvailableVndTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_vnd_subtracts_pending_withdrawal_amounts(): void
    {
        $user = User::factory()->create([
            'balance_vnd' => 160_000,
            'frozen_vnd' => 0,
        ]);

        $this->assertSame(160_000, $user->availableVnd());

        WithdrawalRequest::query()->create([
            'user_id' => $user->getKey(),
            'amount_vnd' => 100_000,
            'bank_name' => 'VCB',
            'bank_account_number' => '1',
            'bank_account_name' => 'A',
            'note' => null,
            'status' => WithdrawalStatus::Pending,
        ]);

        $user->refresh();

        $this->assertSame(60_000, $user->availableVnd());
    }

    public function test_approved_withdrawal_does_not_reduce_available_twice(): void
    {
        $user = User::factory()->create([
            'balance_vnd' => 50_000,
            'frozen_vnd' => 0,
        ]);

        WithdrawalRequest::query()->create([
            'user_id' => $user->getKey(),
            'amount_vnd' => 20_000,
            'bank_name' => 'VCB',
            'bank_account_number' => '1',
            'bank_account_name' => 'A',
            'note' => null,
            'status' => WithdrawalStatus::Approved,
        ]);

        $user->refresh();

        $this->assertSame(50_000, $user->availableVnd());
    }
}

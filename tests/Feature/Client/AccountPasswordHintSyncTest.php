<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPasswordHintSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_password_update_syncs_encrypted_password_hint_for_admin_reveal(): void
    {
        $user = User::factory()->create();
        $user->syncPasswordAndHintFromPlain('old-secret');

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'old-secret',
                'password' => 'new-secret-122333',
                'password_confirmation' => 'new-secret-122333',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-122333', $user->password));
        $this->assertSame('new-secret-122333', $user->password_hint);
    }
}

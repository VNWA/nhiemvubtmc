<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_login_records_last_login_metadata_and_one_activity_log()
    {
        $user = User::factory()->create([
            'last_login_at' => null,
            'last_login_ip' => null,
        ]);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $user->refresh();
        $this->assertNotNull($user->last_login_at, 'last_login_at should be set on login');
        $this->assertNotNull($user->last_login_ip, 'last_login_ip should be set on login');

        // Listener must fire exactly once: there should be a single user.login row.
        $this->assertSame(
            1,
            ActivityLog::query()
                ->where('action', 'user.login')
                ->where('target_user_id', $user->getKey())
                ->count(),
            'user.login activity log should be recorded exactly once per login'
        );
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                Fortify::username() => $user->username,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login.store'), [
            Fortify::username() => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}

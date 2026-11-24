<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('test@example.com|127.0.0.1');
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    public function test_login_successful_with_active_user(): void
    {
        $user = User::factory()->create([
            'email'     => 'test@example.com',
            'password'  => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email'    => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'action'  => 'login',
        ]);
    }

    public function test_login_prevents_user_enumeration_for_wrong_password(): void
    {
        User::factory()->create([
            'email'     => 'test@example.com',
            'password'  => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email'    => 'test@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Kombinasi email atau password yang Anda masukkan salah.']);
        $this->assertGuest();
    }

    public function test_login_prevents_user_enumeration_for_inactive_user(): void
    {
        User::factory()->create([
            'email'     => 'inactive@example.com',
            'password'  => 'Password123!',
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email'    => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('login'));
        // Must return identical generic error, NOT "Akun Anda dinonaktifkan"
        $response->assertSessionHasErrors(['email' => 'Kombinasi email atau password yang Anda masukkan salah.']);
        $this->assertGuest();
    }

    public function test_login_prevents_user_enumeration_for_nonexistent_email(): void
    {
        $response = $this->from(route('login'))->post(route('login'), [
            'email'    => 'nonexistent@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Kombinasi email atau password yang Anda masukkan salah.']);
        $this->assertGuest();
    }

    public function test_login_rate_limiting_blocks_after_five_failed_attempts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->from(route('login'))->post(route('login'), [
                'email'    => 'brute@example.com',
                'password' => 'BadPass' . $i,
            ]);
            $res->assertRedirect(route('login'));
        }

        // 6th attempt must be throttled
        $response = $this->from(route('login'))->post(route('login'), [
            'email'    => 'brute@example.com',
            'password' => 'BadPass6',
        ]);

        // Either controller returns friendly error or route throttle returns 429
        if ($response->getStatusCode() === 429) {
            $this->assertSame(429, $response->getStatusCode());
        } else {
            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors('email');
            $errors = session('errors')->get('email');
            $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
        }
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\AuthOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_otp_and_creates_only_one_user(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Test Player',
            'email' => 'player@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson('/api/auth/register/request-otp', $payload)
            ->assertAccepted()
            ->assertJsonPath('email', 'player@example.com');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('pending_registrations', ['email' => 'player@example.com']);

        AuthOtp::query()
            ->where('email', 'player@example.com')
            ->where('purpose', 'registration')
            ->update(['code_hash' => Hash::make('123456')]);

        $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'player@example.com',
            'otp' => '123456',
        ])
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('pending_registrations', ['email' => 'player@example.com']);

        $this->postJson('/api/auth/register/verify-otp', [
            'email' => 'player@example.com',
            'otp' => '123456',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_request_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'player@example.com']);

        $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Another Player',
            'email' => 'PLAYER@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pending_registrations', 0);
    }

    public function test_login_returns_a_sanctum_token(): void
    {
        User::factory()->create([
            'email' => 'player@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'player@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password/request-otp', [
            'email' => 'missing@example.com',
        ])
            ->assertAccepted()
            ->assertJsonPath(
                'message',
                'If the account exists, a verification code has been sent.',
            );

        $this->assertDatabaseCount('auth_otps', 0);
    }

    public function test_password_can_be_reset_after_valid_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'player@example.com',
            'password' => 'old-password',
        ]);

        $this->postJson('/api/auth/forgot-password/request-otp', [
            'email' => 'player@example.com',
        ])->assertAccepted();

        AuthOtp::query()
            ->where('email', 'player@example.com')
            ->where('purpose', 'password_reset')
            ->update(['code_hash' => Hash::make('654321')]);

        $verification = $this->postJson('/api/auth/forgot-password/verify-otp', [
            'email' => 'player@example.com',
            'otp' => '654321',
        ])->assertOk();

        $resetToken = $verification->json('reset_token');

        $this->postJson('/api/auth/forgot-password/reset', [
            'email' => 'player@example.com',
            'reset_token' => $resetToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password123', $user->refresh()->password));
        $this->assertDatabaseMissing('auth_otps', [
            'email' => 'player@example.com',
            'purpose' => 'password_reset',
        ]);
    }
}

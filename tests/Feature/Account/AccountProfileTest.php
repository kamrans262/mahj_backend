<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_read_and_update_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->putJson('/api/profile', [
            'name' => 'Updated Player',
            'phone' => '123456789',
            'zip_code' => '10001',
            'city' => 'New York',
            'state' => 'NY',
            'bio' => 'Mahj player',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Player')
            ->assertJsonPath('data.zip_code', '10001');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Player',
            'city' => 'New York',
        ]);
    }

    public function test_email_change_preserves_database_uniqueness(): void
    {
        $user = User::factory()->create(['email' => 'first@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/account/email', [
            'email' => 'taken@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->putJson('/api/account/email', [
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertSame('new@example.com', $user->refresh()->email);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->putJson('/api/account/password', [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password123', $user->refresh()->password));
    }

    public function test_account_deletion_removes_the_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}

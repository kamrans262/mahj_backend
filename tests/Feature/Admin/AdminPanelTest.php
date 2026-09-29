<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_dashboard_and_manage_user_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Users & Accounts');

        $this->actingAs($admin)
            ->patch('/admin/users/'.$user->id, [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => '1',
                'is_suspended' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($user->refresh()->is_suspended);
    }
}

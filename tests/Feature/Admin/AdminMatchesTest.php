<?php

namespace Tests\Feature\Admin;

use App\Models\MahjMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_open_and_update_match(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create();
        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'location_address' => 'Central Park, New York',
            'venue_name' => 'Central Park View',
            'starts_at' => now()->addDay(),
            'is_public' => true,
            'is_invite_only' => false,
            'status' => 'open',
            'max_players' => 4,
        ]);
        $match->players()->attach($host->id, ['joined_at' => now()]);

        $this->actingAs($admin)
            ->get('/admin/matches')
            ->assertOk()
            ->assertSeeText('Central Park View');

        $this->actingAs($admin)
            ->get('/admin/matches/'.$match->id)
            ->assertOk()
            ->assertSeeText('Manage Match')
            ->assertSeeText($host->email);

        $this->actingAs($admin)
            ->patch('/admin/matches/'.$match->id, ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame('cancelled', $match->refresh()->status);
        $this->assertNotNull($match->cancelled_at);
    }
}

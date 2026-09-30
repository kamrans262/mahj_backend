<?php

namespace Tests\Feature\Admin;

use App\Models\MahjMatch;
use App\Models\Sport;
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
        $sport = Sport::query()->create([
            'name' => 'Basketball',
            'slug' => 'basketball',
            'icon_key' => 'basketball',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => 'Basketball',
            'sport_id' => $sport->id,
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
            ->assertSeeText('Basketball')
            ->assertSeeText('Central Park View');

        $this->actingAs($admin)
            ->get('/admin/matches/'.$match->id)
            ->assertOk()
            ->assertSeeText('Manage Match')
            ->assertSeeText($host->email);

        $this->actingAs($admin)
            ->patch('/admin/matches/'.$match->id, [
                'sport_id' => $sport->id,
                'location_address' => $match->location_address,
                'venue_name' => 'Updated Court',
                'notes' => 'Bring a ball.',
                'starts_at' => $match->starts_at->format('Y-m-d H:i:s'),
                'status' => 'cancelled',
                'is_public' => '1',
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame('cancelled', $match->status);
        $this->assertSame('Basketball', $match->name);
        $this->assertSame($sport->id, $match->sport_id);
        $this->assertSame('Updated Court', $match->venue_name);
        $this->assertSame('Bring a ball.', $match->notes);
        $this->assertNotNull($match->cancelled_at);
    }
}

<?php

namespace Tests\Feature\Matches;

use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_create_four_player_match(): void
    {
        $host = User::factory()->create();
        $sport = $this->createSport();

        $response = $this->actingAs($host, 'sanctum')
            ->postJson('/api/matches', [
                'sport_id' => $sport->id,
                'location_address' => 'Central Park, New York',
                'venue_name' => 'Central Park View',
                'starts_at' => now()->addDay()->toISOString(),
                'is_public' => true,
                'is_invite_only' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('match.name', 'Basketball')
            ->assertJsonPath('match.sport_name', 'Basketball')
            ->assertJsonPath('match.sport.slug', 'basketball')
            ->assertJsonPath('match.sport_icon_key', 'basketball')
            ->assertJsonPath('match.status', 'open')
            ->assertJsonPath('match.current_players', 1)
            ->assertJsonPath('match.max_players', 4)
            ->assertJsonPath('match.is_host', true)
            ->assertJsonPath('match.is_joined', true);

        $matchId = $response->json('match.id');

        $this->assertDatabaseHas('matches', [
            'id' => $matchId,
            'host_user_id' => $host->id,
            'name' => 'Basketball',
            'sport_id' => $sport->id,
            'max_players' => 4,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('match_players', [
            'match_id' => $matchId,
            'user_id' => $host->id,
        ]);
    }

    public function test_other_sport_can_be_created_with_custom_name(): void
    {
        $host = User::factory()->create();

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/matches', [
                'custom_sport_name' => 'Ultimate Frisbee',
                'location_address' => 'Central Park, New York',
                'starts_at' => now()->addDay()->toISOString(),
                'is_public' => true,
                'is_invite_only' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('match.sport_name', 'Ultimate Frisbee')
            ->assertJsonPath('match.sport', null)
            ->assertJsonPath('match.sport_icon_key', 'generic');

        $this->assertDatabaseHas('matches', [
            'custom_sport_name' => 'Ultimate Frisbee',
            'name' => 'Ultimate Frisbee',
            'sport_id' => null,
        ]);
    }

    public function test_sports_catalog_lists_only_active_sports_in_order(): void
    {
        $active = $this->createSport();
        Sport::query()->create([
            'name' => 'Hidden Sport',
            'slug' => 'hidden-sport',
            'icon_key' => 'generic',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/sports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.slug', 'basketball');
    }

    public function test_public_active_matches_are_available_for_discovery(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $openMatch = $this->createMatch($host);
        $cancelledMatch = $this->createMatch($host);
        $cancelledMatch->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/matches')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains((string) $openMatch->id));
        $this->assertFalse($ids->contains((string) $cancelledMatch->id));
    }

    public function test_match_confirms_automatically_when_fourth_player_joins(): void
    {
        $host = User::factory()->create();
        $players = User::factory()->count(3)->create();
        $match = $this->createMatch($host);

        foreach ($players as $index => $player) {
            $response = $this->actingAs($player, 'sanctum')
                ->postJson("/api/matches/{$match->id}/join")
                ->assertOk();

            $expectedPlayers = $index + 2;
            $response->assertJsonPath('match.current_players', $expectedPlayers);
        }

        $this->assertSame('confirmed', $match->refresh()->status);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/matches/{$match->id}/join")
            ->assertUnprocessable();
    }

    public function test_joined_player_can_leave_and_confirmed_match_reopens(): void
    {
        $host = User::factory()->create();
        $players = User::factory()->count(3)->create();
        $match = $this->createMatch($host);

        foreach ($players as $player) {
            $match->players()->attach($player->id, ['joined_at' => now()]);
        }
        $match->update(['status' => 'confirmed']);

        $leavingPlayer = $players->first();

        $this->actingAs($leavingPlayer, 'sanctum')
            ->postJson("/api/matches/{$match->id}/leave")
            ->assertOk()
            ->assertJsonPath('match.current_players', 3)
            ->assertJsonPath('match.status', 'open')
            ->assertJsonPath('match.is_joined', false);

        $this->assertSame('open', $match->refresh()->status);
    }

    public function test_only_host_can_cancel_match(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $match = $this->createMatch($host);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/matches/{$match->id}/cancel")
            ->assertForbidden();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/cancel")
            ->assertOk()
            ->assertJsonPath('match.status', 'cancelled');

        $this->assertNotNull($match->refresh()->cancelled_at);
    }

    public function test_invite_only_match_is_not_joinable_without_invitation(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $match = $this->createMatch($host, inviteOnly: true);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/matches/{$match->id}/join")
            ->assertUnprocessable();
    }

    private function createSport(): Sport
    {
        return Sport::query()->create([
            'name' => 'Basketball',
            'slug' => 'basketball',
            'icon_key' => 'basketball',
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    private function createMatch(User $host, bool $inviteOnly = false): MahjMatch
    {
        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => 'Basketball',
            'location_address' => 'Central Park, New York',
            'venue_name' => 'Central Park View',
            'starts_at' => now()->addDay(),
            'is_public' => ! $inviteOnly,
            'is_invite_only' => $inviteOnly,
            'status' => 'open',
            'max_players' => 4,
        ]);

        $match->players()->attach($host->id, ['joined_at' => now()]);

        return $match;
    }
}

<?php

namespace Tests\Feature\Profiles;

use App\Models\MahjMatch;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_hides_private_contact_details_and_uses_actual_join_year(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create([
            'email' => 'private-profile@example.test',
            'city' => 'Private City',
        ]);
        $player->forceFill(['created_at' => '2024-04-21 12:00:00'])->save();

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/users/{$player->id}/profile")
            ->assertOk()
            ->assertJsonPath('player.name', $player->name)
            ->assertJsonPath('player.member_since_year', 2024)
            ->assertJsonPath('player.mutual_games_total', 0)
            ->assertJsonCount(0, 'mutual_games');

        $this->assertStringNotContainsString('private-profile@example.test', $response->getContent());
        $this->assertStringNotContainsString('Private City', $response->getContent());
        $response->assertJsonMissingPath('player.email')
            ->assertJsonMissingPath('player.city')
            ->assertJsonMissingPath('player.address');
    }

    public function test_mutual_games_are_only_games_both_players_joined(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create();

        $shared = $this->createMatch($viewer);
        $shared->players()->attach($viewer->id, ['joined_at' => now()]);
        $shared->players()->attach($player->id, ['joined_at' => now()]);

        $notShared = $this->createMatch($viewer);
        $notShared->players()->attach($viewer->id, ['joined_at' => now()]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/users/{$player->id}/profile?per_page=20")
            ->assertOk()
            ->assertJsonPath('player.mutual_games_total', 1)
            ->assertJsonCount(1, 'mutual_games');

        $this->assertSame((string) $shared->id, $response->json('mutual_games.0.id'));
        $this->assertNotSame((string) $notShared->id, $response->json('mutual_games.0.id'));
    }

    public function test_blocking_prevents_player_profile_lookup(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create();
        UserBlock::query()->create([
            'blocker_user_id' => $player->id,
            'blocked_user_id' => $viewer->id,
            'reason' => 'safety_concern',
        ]);
        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/users/{$player->id}/profile")
            ->assertNotFound();
    }

    private function createMatch(User $host): MahjMatch
    {
        return MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => 'Mah Jongg',
            'location_address' => 'Community Hall',
            'starts_at' => now()->addDay(),
            'is_public' => true,
            'is_invite_only' => false,
            'status' => 'open',
            'max_players' => 4,
        ]);
    }
}

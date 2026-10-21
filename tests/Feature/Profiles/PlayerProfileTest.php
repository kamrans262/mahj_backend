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

        // Scheduled for tomorrow, but both players already joined.
        $futureShared = $this->createMatch($viewer);
        $futureShared->players()->attach($viewer->id, ['joined_at' => now()]);
        $futureShared->players()->attach($player->id, ['joined_at' => now()]);

        // Past completed games remain mutual too.
        $pastShared = $this->createMatch($viewer);
        $pastShared->update(['starts_at' => now()->subDay(), 'status' => 'completed']);
        $pastShared->players()->attach($viewer->id, ['joined_at' => now()]);
        $pastShared->players()->attach($player->id, ['joined_at' => now()]);

        $notShared = $this->createMatch($viewer);
        $notShared->players()->attach($viewer->id, ['joined_at' => now()]);

        $cancelled = $this->createMatch($viewer);
        $cancelled->update(['status' => 'cancelled']);
        $cancelled->players()->attach($viewer->id, ['joined_at' => now()]);
        $cancelled->players()->attach($player->id, ['joined_at' => now()]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/users/{$player->id}/profile?per_page=20")
            ->assertOk()
            ->assertJsonPath('player.mutual_games_total', 2)
            ->assertJsonCount(2, 'mutual_games');

        $mutualIds = collect($response->json('mutual_games'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing(
            [(string) $futureShared->id, (string) $pastShared->id],
            $mutualIds,
        );
        $this->assertNotContains((string) $notShared->id, $mutualIds);
        $this->assertNotContains((string) $cancelled->id, $mutualIds);
    }

    public function test_profile_statistics_respond_to_real_join_host_and_completion_changes(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create();

        $futureHosted = $this->createMatch($player);
        $futureHosted->players()->attach($player->id, ['joined_at' => now()]);

        $pastHosted = $this->createMatch($player);
        $pastHosted->update(['starts_at' => now()->subDays(3), 'status' => 'completed']);
        $pastHosted->players()->attach($player->id, ['joined_at' => now()]);

        $pastJoined = $this->createMatch($viewer);
        $pastJoined->update(['starts_at' => now()->subDay()]);
        $pastJoined->players()->attach($player->id, ['joined_at' => now()]);

        $cancelled = $this->createMatch($player);
        $cancelled->update(['starts_at' => now()->subDay(), 'status' => 'cancelled']);
        $cancelled->players()->attach($player->id, ['joined_at' => now()]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/users/{$player->id}/profile")
            ->assertOk()
            ->assertJsonPath('player.matches_played', 2)
            ->assertJsonPath('player.matches_hosted', 1)
            ->assertJsonPath('player.attendance_percent', 50);

        // As the server records completion, the live stats change.
        $pastJoined->update(['status' => 'completed']);

        $this->getJson("/api/users/{$player->id}/profile")
            ->assertOk()
            ->assertJsonPath('player.matches_played', 2)
            ->assertJsonPath('player.matches_hosted', 1)
            ->assertJsonPath('player.attendance_percent', 100);
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

<?php

namespace Tests\Feature\Favorites;

use App\Models\MahjMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_by_match_only_displays_upcoming_non_cancelled_saved_games(): void
    {
        $viewer = User::factory()->create();
        $upcoming = $this->createMatch($viewer, now()->addDays(2));
        $confirmed = $this->createMatch($viewer, now()->addDays(3), 'confirmed');
        $past = $this->createMatch($viewer, now()->subDay());
        $completed = $this->createMatch($viewer, now()->subDays(2), 'completed');
        $futureButCompleted = $this->createMatch($viewer, now()->addDays(4), 'completed');
        $cancelled = $this->createMatch($viewer, now()->addDays(5), 'cancelled');

        foreach ([$upcoming, $confirmed, $past, $completed, $futureButCompleted, $cancelled] as $match) {
            $viewer->favoriteMatches()->syncWithoutDetaching([$match->id]);
        }

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing(
            [(string) $upcoming->id, (string) $confirmed->id],
            $ids,
        );

        // Filtering is presentation only: historical favorites are preserved.
        foreach ([$past, $completed, $futureButCompleted, $cancelled] as $match) {
            $this->assertDatabaseHas('match_favorites', [
                'user_id' => $viewer->id,
                'match_id' => $match->id,
            ]);
        }
    }

    public function test_by_player_and_profile_agree_on_real_past_mutual_games(): void
    {
        $viewer = User::factory()->create();
        $favorite = User::factory()->create();
        $other = User::factory()->create();
        $viewer->favoritePlayers()->syncWithoutDetaching([$favorite->id]);

        // Shared match in the past, explicitly completed.
        $shared = $this->createMatch($viewer, now()->subDays(3), 'completed');
        $this->join($shared, $favorite);

        // Historical match without the favorite: not mutual.
        $notShared = $this->createMatch($viewer, now()->subDays(2));
        $this->join($notShared, $other);

        // Future and cancelled games never count as played together.
        $future = $this->createMatch($viewer, now()->addDays(2));
        $this->join($future, $favorite);
        $cancelled = $this->createMatch($viewer, now()->subDay(), 'cancelled');
        $this->join($cancelled, $favorite);

        $favorites = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/favorite-players')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mutual_games_count', 1)
            ->assertJsonPath('data.0.games_count', 1);

        $this->assertSame((string) $favorite->id, $favorites->json('data.0.id'));

        $profile = $this->getJson("/api/users/{$favorite->id}/profile")
            ->assertOk()
            ->assertJsonPath('player.mutual_games_total', 1)
            ->assertJsonCount(1, 'mutual_games');

        $this->assertSame((string) $shared->id, $profile->json('mutual_games.0.id'));
    }

    public function test_favorite_player_remains_saved_after_her_shared_games_end(): void
    {
        $viewer = User::factory()->create();
        $favorite = User::factory()->create();
        $viewer->favoritePlayers()->syncWithoutDetaching([$favorite->id]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/favorite-players')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mutual_games_count', 0);

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $viewer->id,
            'favorite_user_id' => $favorite->id,
        ]);
    }

    private function createMatch(User $host, Carbon $startsAt, string $status = 'open'): MahjMatch
    {
        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => 'Mah Jongg',
            'location_address' => 'Community Hall',
            'starts_at' => $startsAt,
            'status' => $status,
            'max_players' => 4,
            'is_public' => true,
            'is_invite_only' => false,
        ]);
        $this->join($match, $host);

        return $match;
    }

    private function join(MahjMatch $match, User $player): void
    {
        $match->players()->attach($player->id, ['joined_at' => now()]);
    }
}

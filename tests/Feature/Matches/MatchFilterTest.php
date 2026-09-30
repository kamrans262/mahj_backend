<?php

namespace Tests\Feature\Matches;

use App\Models\MahjMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_radius_filter_returns_distance_and_excludes_matches_outside_radius(): void
    {
        $viewer = User::factory()->create();
        $near = $this->createMatch(
            User::factory()->create(),
            'Near Court',
            40.7860,
            -73.9683,
            now()->addDay(),
        );
        $far = $this->createMatch(
            User::factory()->create(),
            'Far Court',
            40.8700,
            -73.9500,
            now()->addDay()->addHour(),
        );

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/matches?latitude=40.785091&longitude=-73.968285&radius_miles=2&sort=distance&discover_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $near->id);

        $this->assertIsNumeric($response->json('data.0.distance_miles'));
        $this->assertNotSame((string) $far->id, $response->json('data.0.id'));
    }

    public function test_open_spots_search_and_date_sort_filters_are_applied(): void
    {
        $viewer = User::factory()->create();
        $open = $this->createMatch(
            User::factory()->create(),
            'Riverside Basketball',
            40.8000,
            -73.9700,
            now()->addHours(6),
        );
        $full = $this->createMatch(
            User::factory()->create(),
            'Riverside Full',
            40.8010,
            -73.9710,
            now()->addHours(5),
        );

        $extraPlayers = User::factory()->count(3)->create();
        foreach ($extraPlayers as $player) {
            $full->players()->attach($player->id, ['joined_at' => now()]);
        }

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/matches?q=Riverside&open_spots_only=1&date_filter=today&sort=date&timezone_offset_minutes=0&discover_only=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains((string) $open->id));
        $this->assertFalse($ids->contains((string) $full->id));
    }

    public function test_discovery_only_excludes_matches_owned_or_joined_by_current_user(): void
    {
        $viewer = User::factory()->create();
        $owned = $this->createMatch($viewer, 'Owned Court', 40.78, -73.96, now()->addDay());
        $joined = $this->createMatch(
            User::factory()->create(),
            'Joined Court',
            40.79,
            -73.97,
            now()->addDay(),
        );
        $joined->players()->attach($viewer->id, ['joined_at' => now()]);
        $discoverable = $this->createMatch(
            User::factory()->create(),
            'Discoverable Court',
            40.80,
            -73.98,
            now()->addDay(),
        );

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/matches?discover_only=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains((string) $owned->id));
        $this->assertFalse($ids->contains((string) $joined->id));
        $this->assertTrue($ids->contains((string) $discoverable->id));
    }

    private function createMatch(
        User $host,
        string $venue,
        float $latitude,
        float $longitude,
        $startsAt,
    ): MahjMatch {
        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => 'Basketball',
            'location_address' => $venue.', New York, NY',
            'venue_name' => $venue,
            'starts_at' => $startsAt,
            'is_public' => true,
            'is_invite_only' => false,
            'status' => 'open',
            'max_players' => 4,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        $match->players()->attach($host->id, ['joined_at' => now()]);

        return $match;
    }
}

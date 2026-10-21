<?php

namespace Tests\Feature\Matches;

use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_complete_started_match_but_other_user_cannot(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $match = $this->createMatch($host, startsAt: now()->subHour());

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/matches/{$match->id}/complete")
            ->assertForbidden();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/complete")
            ->assertOk()
            ->assertJsonPath('match.status', 'completed');

        $this->assertSame('completed', $match->refresh()->status);
        $this->assertNotNull($match->completed_at);
    }

    public function test_future_match_cannot_be_completed(): void
    {
        $host = User::factory()->create();
        $match = $this->createMatch($host, startsAt: now()->addHour());

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('match');
    }

    public function test_only_host_can_save_and_change_scores_for_every_player(): void
    {
        $host = User::factory()->create();
        $players = User::factory()->count(2)->create();
        $match = $this->createMatch(
            $host,
            startsAt: now()->subHours(2),
            status: 'completed',
        );

        foreach ($players as $player) {
            $match->players()->attach($player->id, ['joined_at' => now()->subHours(3)]);
        }

        $payload = [
            'scores' => [
                ['player_id' => $host->id, 'score' => 21],
                ['player_id' => $players[0]->id, 'score' => 18],
                ['player_id' => $players[1]->id, 'score' => 15],
            ],
        ];

        $submitter = $players->first();
        $this->actingAs($submitter, 'sanctum')
            ->postJson("/api/matches/{$match->id}/scores", $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('match_scores', 0);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/scores", $payload)
            ->assertOk()
            ->assertJsonPath('scores_submitted', true)
            ->assertJsonPath('can_submit_scores', true)
            ->assertJsonCount(3, 'players');

        $this->assertDatabaseHas('match_scores', [
            'match_id' => $match->id,
            'player_user_id' => $host->id,
            'score' => 21,
            'submitted_by_user_id' => $host->id,
        ]);

        $changedPayload = [
            'scores' => [
                ['player_id' => $host->id, 'score' => 30],
                ['player_id' => $players[0]->id, 'score' => 22],
                ['player_id' => $players[1]->id, 'score' => 16],
            ],
        ];

        // Previous submissions do not lock a host out of correcting scores.
        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/scores", $changedPayload)
            ->assertOk()
            ->assertJsonPath('scores_submitted', true)
            ->assertJsonPath('can_submit_scores', true)
            ->assertJsonPath('players.0.score', 30);

        $this->assertDatabaseCount('match_scores', 3);
        $this->assertDatabaseHas('match_scores', [
            'match_id' => $match->id,
            'player_user_id' => $host->id,
            'score' => 30,
            'submitted_by_user_id' => $host->id,
        ]);

        $this->actingAs($submitter, 'sanctum')
            ->postJson("/api/matches/{$match->id}/scores", $payload)
            ->assertForbidden();

        // Saved history is shared read-only with players.
        $this->getJson("/api/matches/{$match->id}/completion")
            ->assertOk()
            ->assertJsonPath('scores_submitted', true)
            ->assertJsonPath('can_submit_scores', false)
            ->assertJsonPath('players.0.score', 30);

        $past = $this->actingAs($host, 'sanctum')
            ->getJson('/api/my-matches')
            ->assertOk();
        $this->assertContains(
            (string) $match->id,
            collect($past->json('completed'))->pluck('id')->all(),
        );
    }

    public function test_only_match_participants_can_view_or_submit_completed_scores(): void
    {
        $host = User::factory()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();
        $match = $this->createMatch(
            $host,
            startsAt: now()->subHours(2),
            status: 'completed',
        );
        $match->players()->attach($player->id, ['joined_at' => now()->subHours(3)]);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/matches/{$match->id}/completion")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/matches/{$match->id}/scores", [
                'scores' => [
                    ['player_id' => $host->id, 'score' => 10],
                    ['player_id' => $player->id, 'score' => 8],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/matches/{$match->id}/completion")
            ->assertOk()
            ->assertJsonPath('match.status', 'completed')
            ->assertJsonPath('can_submit_scores', false);

        $this->actingAs($host, 'sanctum')
            ->getJson("/api/matches/{$match->id}/completion")
            ->assertOk()
            ->assertJsonPath('can_submit_scores', true);
    }

    public function test_score_submission_requires_exact_completed_match_players(): void
    {
        $host = User::factory()->create();
        $player = User::factory()->create();
        $match = $this->createMatch(
            $host,
            startsAt: now()->subHours(2),
            status: 'completed',
        );
        $match->players()->attach($player->id, ['joined_at' => now()->subHours(3)]);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/scores", [
                'scores' => [
                    ['player_id' => $host->id, 'score' => 10],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scores');

        $this->assertDatabaseCount('match_scores', 0);
    }

    public function test_my_matches_separates_completed_and_cancelled_history(): void
    {
        $host = User::factory()->create();

        $active = $this->createMatch($host, startsAt: now()->addHour());
        $completed = $this->createMatch(
            $host,
            startsAt: now()->subDay(),
            status: 'completed',
        );
        $cancelled = $this->createMatch(
            $host,
            startsAt: now()->subDays(2),
            status: 'cancelled',
        );

        $response = $this->actingAs($host, 'sanctum')
            ->getJson('/api/my-matches')
            ->assertOk();

        $createdIds = collect($response->json('created_by_me'))->pluck('id');
        $completedIds = collect($response->json('completed'))->pluck('id');
        $cancelledIds = collect($response->json('cancelled'))->pluck('id');

        $this->assertTrue($createdIds->contains((string) $active->id));
        $this->assertFalse($createdIds->contains((string) $completed->id));
        $this->assertFalse($createdIds->contains((string) $cancelled->id));
        $this->assertTrue($completedIds->contains((string) $completed->id));
        $this->assertTrue($cancelledIds->contains((string) $cancelled->id));
    }

    public function test_my_matches_past_games_includes_elapsed_games_without_auto_completion(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();

        $pastOpen = $this->createMatch($host, startsAt: now()->subDay(), status: 'open');
        $pastConfirmed = $this->createMatch($host, startsAt: now()->subHours(2), status: 'confirmed');
        $futureOpen = $this->createMatch($host, startsAt: now()->addDay(), status: 'open');
        $pastCancelled = $this->createMatch($host, startsAt: now()->subDays(2), status: 'cancelled');
        $pastCompleted = $this->createMatch($host, startsAt: now()->subDays(3), status: 'completed');
        $pastConfirmed->players()->attach($joiner->id, ['joined_at' => now()]);

        $hostResponse = $this->actingAs($host, 'sanctum')
            ->getJson('/api/my-matches')
            ->assertOk();

        $pastIds = collect($hostResponse->json('completed'))->pluck('id');
        $createdIds = collect($hostResponse->json('created_by_me'))->pluck('id');
        $cancelledIds = collect($hostResponse->json('cancelled'))->pluck('id');

        $this->assertTrue($pastIds->contains((string) $pastOpen->id));
        $this->assertTrue($pastIds->contains((string) $pastConfirmed->id));
        $this->assertTrue($pastIds->contains((string) $pastCompleted->id));
        $this->assertFalse($pastIds->contains((string) $pastCancelled->id));
        $this->assertFalse($pastIds->contains((string) $futureOpen->id));
        $this->assertTrue($createdIds->contains((string) $futureOpen->id));
        $this->assertFalse($createdIds->contains((string) $pastOpen->id));
        $this->assertTrue($cancelledIds->contains((string) $pastCancelled->id));

        $joinedResponse = $this->actingAs($joiner, 'sanctum')
            ->getJson('/api/my-matches')
            ->assertOk();
        $this->assertContains((string) $pastConfirmed->id, collect($joinedResponse->json('completed'))->pluck('id')->all());
        $this->assertNotContains((string) $pastConfirmed->id, collect($joinedResponse->json('upcoming'))->pluck('id')->all());

        $this->assertSame('open', $pastOpen->refresh()->status);
        $this->assertSame('confirmed', $pastConfirmed->refresh()->status);
    }

    public function test_upcoming_includes_hosted_and_joined_future_games_without_duplicates(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->createMatch($host, now()->addDay(), 'open');
        $joined = $this->createMatch($other, now()->addDays(2), 'confirmed');
        $joined->players()->attach($host->id, ['joined_at' => now()]);

        $past = $this->createMatch($host, now()->subHour(), 'open');
        $cancelled = $this->createMatch($host, now()->addDays(3), 'cancelled');
        $response = $this->actingAs($host, 'sanctum')
            ->getJson('/api/my-matches')->assertOk();

        $upcoming = collect($response->json('upcoming'))->pluck('id');
        $created = collect($response->json('created_by_me'))->pluck('id');

        $this->assertContains((string) $own->id, $upcoming->all());
        $this->assertContains((string) $joined->id, $upcoming->all());
        $this->assertSame(1, $upcoming->filter(fn ($id) => $id === (string) $own->id)->count());
        $this->assertNotContains((string) $past->id, $upcoming->all());
        $this->assertNotContains((string) $cancelled->id, $upcoming->all());
        $this->assertContains((string) $own->id, $created->all());
        $this->assertNotContains((string) $joined->id, $created->all());
    }

    private function createMatch(
        User $host,
        \DateTimeInterface $startsAt,
        string $status = 'confirmed',
    ): MahjMatch {
        $sport = Sport::query()->firstOrCreate(
            ['slug' => 'basketball'],
            [
                'name' => 'Basketball',
                'icon_key' => 'basketball',
                'is_active' => true,
                'sort_order' => 20,
            ],
        );

        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => $sport->name,
            'sport_id' => $sport->id,
            'location_address' => 'Multan, Punjab, Pakistan',
            'venue_name' => 'M8 Test Court '.uniqid(),
            'starts_at' => $startsAt,
            'is_public' => true,
            'is_invite_only' => false,
            'status' => $status,
            'max_players' => 4,
            'completed_at' => $status === 'completed' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
        ]);

        $match->players()->attach($host->id, ['joined_at' => now()->subHours(3)]);

        return $match;
    }
}

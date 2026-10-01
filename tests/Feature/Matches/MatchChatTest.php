<?php

namespace Tests\Feature\Matches;

use App\Models\MahjMatch;
use App\Models\MatchChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_participant_can_load_and_send_persisted_chat_messages(): void
    {
        $host = User::factory()->create(['name' => 'Host Player']);
        $player = User::factory()->create(['name' => 'Joined Player']);
        $match = $this->createMatch($host);
        $match->players()->attach($player->id, ['joined_at' => now()]);

        MatchChatMessage::query()->create([
            'match_id' => $match->id,
            'sender_user_id' => null,
            'type' => 'system',
            'body' => 'Match chat is ready',
        ]);

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/matches/{$match->id}/chat")
            ->assertOk()
            ->assertJsonPath('meta.can_send', true)
            ->assertJsonPath('messages.0.type', 'system')
            ->assertJsonCount(2, 'participants');

        $response = $this->actingAs($player, 'sanctum')
            ->postJson("/api/matches/{$match->id}/chat/messages", [
                'text' => '  See you at the match.  ',
            ])
            ->assertCreated()
            ->assertJsonPath('message.text', 'See you at the match.')
            ->assertJsonPath('message.sender.id', (string) $player->id)
            ->assertJsonPath('message.type', 'message');

        $messageId = $response->json('message.id');

        $this->assertDatabaseHas('match_chat_messages', [
            'id' => $messageId,
            'match_id' => $match->id,
            'sender_user_id' => $player->id,
            'body' => 'See you at the match.',
            'type' => 'message',
        ]);
    }

    public function test_non_participant_cannot_read_or_send_match_chat(): void
    {
        $host = User::factory()->create();
        $outsider = User::factory()->create();
        $match = $this->createMatch($host);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/matches/{$match->id}/chat")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/matches/{$match->id}/chat/messages", [
                'text' => 'I should not be here',
            ])
            ->assertForbidden();
    }

    public function test_chat_supports_older_message_pagination_and_incremental_updates(): void
    {
        $host = User::factory()->create();
        $match = $this->createMatch($host);

        foreach (range(1, 5) as $number) {
            MatchChatMessage::query()->create([
                'match_id' => $match->id,
                'sender_user_id' => $host->id,
                'type' => 'message',
                'body' => 'Message '.$number,
            ]);
        }

        $latest = $this->actingAs($host, 'sanctum')
            ->getJson("/api/matches/{$match->id}/chat?limit=2")
            ->assertOk()
            ->assertJsonPath('meta.has_more_older', true);

        $latestIds = collect($latest->json('messages'))->pluck('id');
        $this->assertCount(2, $latestIds);

        $oldestLatestId = (int) $latestIds->first();

        $older = $this->actingAs($host, 'sanctum')
            ->getJson("/api/matches/{$match->id}/chat?limit=2&before_id={$oldestLatestId}")
            ->assertOk();

        $this->assertCount(2, $older->json('messages'));

        $newMessage = MatchChatMessage::query()->create([
            'match_id' => $match->id,
            'sender_user_id' => $host->id,
            'type' => 'message',
            'body' => 'Newest message',
        ]);

        $after = $this->actingAs($host, 'sanctum')
            ->getJson("/api/matches/{$match->id}/chat?after_id={$latestIds->last()}")
            ->assertOk();

        $this->assertSame(
            [(string) $newMessage->id],
            collect($after->json('messages'))->pluck('id')->all(),
        );
    }

    public function test_cancelled_or_completed_match_chat_is_read_only(): void
    {
        $host = User::factory()->create();
        $match = $this->createMatch($host);
        $match->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->actingAs($host, 'sanctum')
            ->getJson("/api/matches/{$match->id}/chat")
            ->assertOk()
            ->assertJsonPath('meta.can_send', false);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/chat/messages", [
                'text' => 'Too late',
            ])
            ->assertUnprocessable();
    }

    public function test_join_leave_confirmation_and_cancel_create_system_events(): void
    {
        $host = User::factory()->create(['name' => 'Host']);
        $players = User::factory()->count(3)->create();
        $match = $this->createMatch($host);

        foreach ($players as $player) {
            $this->actingAs($player, 'sanctum')
                ->postJson("/api/matches/{$match->id}/join")
                ->assertOk();
        }

        $this->assertDatabaseHas('match_chat_messages', [
            'match_id' => $match->id,
            'type' => 'system',
            'body' => $players->last()->name.' joined the match',
        ]);
        $this->assertDatabaseHas('match_chat_messages', [
            'match_id' => $match->id,
            'type' => 'system',
            'body' => 'Match confirmed',
        ]);

        $leaving = $players->first();
        $this->actingAs($leaving, 'sanctum')
            ->postJson("/api/matches/{$match->id}/leave")
            ->assertOk();

        $this->assertDatabaseHas('match_chat_messages', [
            'match_id' => $match->id,
            'type' => 'system',
            'body' => $leaving->name.' left the match',
        ]);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('match_chat_messages', [
            'match_id' => $match->id,
            'type' => 'system',
            'body' => 'Match cancelled',
        ]);
    }

    private function createMatch(User $host): MahjMatch
    {
        $match = MahjMatch::query()->create([
            'host_user_id' => $host->id,
            'name' => 'Basketball',
            'location_address' => 'Central Park, New York',
            'venue_name' => 'Central Park View',
            'starts_at' => now()->addDay(),
            'is_public' => true,
            'is_invite_only' => false,
            'status' => 'open',
            'max_players' => 4,
        ]);

        $match->players()->attach($host->id, ['joined_at' => now()]);

        return $match;
    }
}

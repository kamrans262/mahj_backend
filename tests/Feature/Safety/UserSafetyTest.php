<?php

namespace Tests\Feature\Safety;

use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_safety_endpoint_returns_only_current_users_blocks_and_reports(): void
    {
        $current = User::factory()->create(['email' => 'current@example.test']);
        $blocked = User::factory()->create(['email' => 'blocked.person@example.test']);
        $reported = User::factory()->create(['email' => 'reported.person@example.test']);
        $outsider = User::factory()->create();

        UserBlock::query()->create([
            'blocker_user_id' => $current->id,
            'blocked_user_id' => $blocked->id,
            'reason' => 'safety_concern',
        ]);

        UserReport::query()->create([
            'reporter_user_id' => $current->id,
            'reported_user_id' => $reported->id,
            'reason' => 'inappropriate_behavior',
            'status' => 'pending',
        ]);

        UserReport::query()->create([
            'reporter_user_id' => $current->id,
            'reported_user_id' => $blocked->id,
            'reason' => 'safety_concern',
            'status' => 'closed',
            'reviewed_at' => now(),
        ]);

        UserReport::query()->create([
            'reporter_user_id' => $outsider->id,
            'reported_user_id' => $current->id,
            'reason' => 'other',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($current, 'sanctum')
            ->getJson('/api/safety')
            ->assertOk()
            ->assertJsonCount(1, 'blocked_users')
            ->assertJsonCount(2, 'report_history')
            ->assertJsonPath('blocked_users.0.id', (string) $blocked->id)
            ->assertJsonPath('blocked_users.0.username', 'blocked.person');

        $statuses = collect($response->json('report_history'))->pluck('status');
        $this->assertTrue($statuses->contains('pending'));
        $this->assertTrue($statuses->contains('closed'));
    }

    public function test_user_can_report_block_and_unblock_another_player_but_not_self(): void
    {
        $current = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($current, 'sanctum')
            ->postJson("/api/users/{$other->id}/report", [
                'reason_id' => 'safety_concern',
                'notes' => 'Unsafe conduct.',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('user_reports', [
            'reporter_user_id' => $current->id,
            'reported_user_id' => $other->id,
            'reason' => 'safety_concern',
            'status' => 'pending',
        ]);

        $this->actingAs($current, 'sanctum')
            ->postJson("/api/users/{$other->id}/block", [
                'reason_id' => 'safety_concern',
            ])
            ->assertOk()
            ->assertJsonPath('blocked', true);

        $this->actingAs($current, 'sanctum')
            ->postJson("/api/users/{$other->id}/block", [
                'reason_id' => 'inappropriate_behavior',
            ])
            ->assertOk();

        $this->assertDatabaseCount('user_blocks', 1);
        $this->assertDatabaseHas('user_blocks', [
            'blocker_user_id' => $current->id,
            'blocked_user_id' => $other->id,
            'reason' => 'inappropriate_behavior',
        ]);

        $this->actingAs($current, 'sanctum')
            ->deleteJson("/api/users/{$other->id}/block")
            ->assertOk()
            ->assertJsonPath('blocked', false);

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_user_id' => $current->id,
            'blocked_user_id' => $other->id,
        ]);

        $this->actingAs($current, 'sanctum')
            ->postJson("/api/users/{$current->id}/block", [
                'reason_id' => 'other',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->actingAs($current, 'sanctum')
            ->postJson("/api/users/{$current->id}/report", [
                'reason_id' => 'other',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_blocked_player_cannot_discover_or_open_blockers_match(): void
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Basketball',
            'slug' => 'basketball',
            'icon_key' => 'basketball',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $match = MahjMatch::query()->create([
            'host_user_id' => $blocker->id,
            'name' => 'Basketball',
            'sport_id' => $sport->id,
            'location_address' => 'Multan, Punjab, Pakistan',
            'venue_name' => 'M9 Safety Court',
            'starts_at' => now()->addHour(),
            'is_public' => true,
            'is_invite_only' => false,
            'status' => 'open',
            'max_players' => 4,
        ]);
        $match->players()->attach($blocker->id, ['joined_at' => now()]);

        UserBlock::query()->create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
            'reason' => 'safety_concern',
        ]);

        $response = $this->actingAs($blocked, 'sanctum')
            ->getJson('/api/matches?discover_only=1')
            ->assertOk();

        $this->assertFalse(
            collect($response->json('matches'))->pluck('id')->contains((string) $match->id),
        );

        $this->actingAs($blocked, 'sanctum')
            ->getJson("/api/matches/{$match->id}")
            ->assertNotFound();
    }

    public function test_admin_can_close_and_reopen_user_report(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $reporter = User::factory()->create();
        $reported = User::factory()->create();

        $report = UserReport::query()->create([
            'reporter_user_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'reason' => 'safety_concern',
            'notes' => 'Admin moderation test.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch("/admin/reports/{$report->id}", ['status' => 'closed'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('closed', $report->status);
        $this->assertNotNull($report->reviewed_at);

        $this->actingAs($admin)
            ->get('/admin/reports?status=closed')
            ->assertOk()
            ->assertSeeText($reported->email)
            ->assertSeeText('Closed');

        $this->actingAs($admin)
            ->patch("/admin/reports/{$report->id}", ['status' => 'pending'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->reviewed_at);
    }
}

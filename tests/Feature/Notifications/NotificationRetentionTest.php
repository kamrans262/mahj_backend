<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_and_unread_notifications_are_visible_only_within_30_days(): void
    {
        $user = User::factory()->create();
        $currentUnread = $this->notification($user);
        $currentRead = $this->notification($user, read: true);
        $expiredUnread = $this->notification($user, daysOld: 31);
        $expiredRead = $this->notification($user, daysOld: 31, read: true);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $currentUnread->id, $ids);
        $this->assertContains((string) $currentRead->id, $ids);
        $this->assertNotContains((string) $expiredUnread->id, $ids);
        $this->assertNotContains((string) $expiredRead->id, $ids);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notifications/'.$expiredUnread->id.'/read')
            ->assertNotFound();
    }

    public function test_player_must_tap_delete_and_can_only_delete_their_own_notification(): void
    {
        $player = User::factory()->create();
        $anotherPlayer = User::factory()->create();
        $own = $this->notification($player);
        $other = $this->notification($anotherPlayer);

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/notifications/'.$other->id)
            ->assertNotFound();

        $this->assertDatabaseHas('user_notifications', ['id' => $other->id]);

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/notifications/'.$own->id)
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseMissing('user_notifications', ['id' => $own->id]);
        $this->assertDatabaseHas('user_notifications', ['id' => $other->id]);
    }

    public function test_daily_prune_command_permanently_removes_only_expired_notifications(): void
    {
        $player = User::factory()->create();
        $keep = $this->notification($player, daysOld: 29);
        $removeRead = $this->notification($player, daysOld: 31, read: true);
        $removeUnread = $this->notification($player, daysOld: 31);

        $this->artisan('mahj:prune-notifications')->assertExitCode(0);

        $this->assertDatabaseHas('user_notifications', ['id' => $keep->id]);
        $this->assertDatabaseMissing('user_notifications', ['id' => $removeRead->id]);
        $this->assertDatabaseMissing('user_notifications', ['id' => $removeUnread->id]);
    }

    private function notification(
        User $user,
        int $daysOld = 0,
        bool $read = false,
    ): UserNotification {
        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'nearby_match',
            'title' => 'New Match Nearby',
            'message' => 'A Mahj match is available.',
            'read_at' => $read ? now() : null,
        ]);

        if ($daysOld > 0) {
            $notification->forceFill([
                'created_at' => now()->subDays($daysOld),
            ])->save();
        }

        return $notification;
    }
}

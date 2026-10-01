<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\MatchInvitation;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\MahjNotificationService;
use Illuminate\Database\Seeder;

class M7NotificationsDemoSeeder extends Seeder
{
    private const TARGET_EMAIL_PREFIX = 'map.view';

    public function run(): void
    {
        $target = User::query()
            ->where('email', 'like', self::TARGET_EMAIL_PREFIX.'%')
            ->orderBy('id')
            ->first();

        if ($target === null) {
            $this->command?->warn(
                'M7 notifications demo data skipped: no existing account starts with '.
                self::TARGET_EMAIL_PREFIX.'.',
            );

            return;
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $target->id],
            [
                'new_games_nearby' => true,
                'game_invitations' => true,
                'players_joining_my_game' => true,
                'game_confirmations' => true,
                'game_reminders' => true,
                'schedule_changes' => true,
                'new_messages' => true,
                'subscription_updates' => true,
            ],
        );

        $match = MahjMatch::query()
            ->whereHas('players', fn ($query) => $query->whereKey($target->id))
            ->orderByDesc('id')
            ->first()
            ?? MahjMatch::query()->orderByDesc('id')->first();

        if ($match === null) {
            $this->command?->warn('M7 notifications demo data skipped: no match exists.');

            return;
        }

        $other = User::query()
            ->where('id', '!=', $target->id)
            ->where('is_admin', false)
            ->orderBy('id')
            ->first();

        $notifications = app(MahjNotificationService::class);
        $base = 'm7-demo:'.$target->id.':'.$match->id;

        $definitions = [
            ['nearby_match', 'New Match Nearby', 'A new match is available near you.'],
            ['player_joined', 'Player Joined Your Game', 'A player joined your game.'],
            ['match_confirmed', 'Game Confirmed', 'Your game is confirmed.'],
            ['match_cancelled', 'Game Cancelled', 'A game you joined was cancelled.'],
            ['schedule_changed', 'Schedule Changed', 'The game date or time changed.'],
            ['chat_message', 'New Message', 'You have a new match chat message.'],
            ['game_reminder', 'Game Reminder', 'Your game starts in about 1 hour.'],
            ['subscription_update', 'Subscription Update', 'Your Mahj subscription was updated.'],
        ];

        foreach ($definitions as [$type, $title, $message]) {
            $notifications->send(
                recipient: $target,
                type: $type,
                title: $title,
                message: $message,
                match: $type === 'subscription_update' ? null : $match,
                relatedUser: $other,
                eventKey: $base.':'.$type,
            );
        }

        $pendingInvitation = MatchInvitation::query()
            ->with(['invitee', 'inviter', 'match'])
            ->where('invitee_user_id', $target->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pendingInvitation !== null) {
            $notifications->matchInvitation($pendingInvitation);
        }

        $this->command?->info(
            'M7 notification demo data ready for '.$target->email.'.',
        );
    }
}

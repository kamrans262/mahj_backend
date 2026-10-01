<?php

namespace App\Services;

use App\Models\MahjMatch;
use App\Models\MatchChatMessage;
use App\Models\MatchInvitation;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserSubscription;
use Illuminate\Support\Collection;

class MahjNotificationService
{
    public function __construct(
        private readonly FirebasePushService $push,
    ) {
    }

    private const PREFERENCE_BY_TYPE = [
        'nearby_match' => 'new_games_nearby',
        'match_invite' => 'game_invitations',
        'player_joined' => 'players_joining_my_game',
        'match_confirmed' => 'game_confirmations',
        'game_reminder' => 'game_reminders',
        'schedule_changed' => 'schedule_changes',
        'chat_message' => 'new_messages',
        'subscription_update' => 'subscription_updates',
    ];

    public function nearbyMatch(MahjMatch $match): void
    {
        if (! $match->is_public || $match->is_invite_only) {
            return;
        }

        User::query()
            ->where('id', '!=', $match->host_user_id)
            ->where('is_admin', false)
            ->where('is_suspended', false)
            ->chunkById(100, function (Collection $users) use ($match): void {
                foreach ($users as $user) {
                    $this->send(
                        recipient: $user,
                        type: 'nearby_match',
                        title: 'New Match Nearby',
                        message: $this->matchLabel($match).' is available near you.',
                        match: $match,
                        eventKey: 'nearby-match:'.$match->id,
                    );
                }
            });
    }

    public function matchInvitation(MatchInvitation $invitation): void
    {
        $invitation->loadMissing(['invitee', 'inviter', 'match']);

        if ($invitation->invitee === null || $invitation->match === null) {
            return;
        }

        $this->send(
            recipient: $invitation->invitee,
            type: 'match_invite',
            title: 'Game Invitation',
            message: ($invitation->inviter?->name ?? 'A player').' invited you to '.$this->matchLabel($invitation->match).'.',
            match: $invitation->match,
            relatedUser: $invitation->inviter,
            eventKey: 'match-invite:'.$invitation->id,
            data: ['invitation_id' => (string) $invitation->id],
        );
    }

    public function playerJoined(MahjMatch $match, User $player): void
    {
        if ($match->host_user_id === $player->id) {
            return;
        }

        $host = User::query()->find($match->host_user_id);
        if ($host === null) {
            return;
        }

        $this->send(
            recipient: $host,
            type: 'player_joined',
            title: 'Player Joined Your Game',
            message: $player->name.' joined '.$this->matchLabel($match).'.',
            match: $match,
            relatedUser: $player,
            eventKey: 'player-joined:'.$match->id.':'.$player->id,
        );
    }

    public function matchConfirmed(MahjMatch $match): void
    {
        $this->forMatchPlayers(
            $match,
            function (User $player) use ($match): void {
                $this->send(
                    recipient: $player,
                    type: 'match_confirmed',
                    title: 'Game Confirmed',
                    message: $this->matchLabel($match).' is confirmed.',
                    match: $match,
                    eventKey: 'match-confirmed:'.$match->id,
                );
            },
        );
    }

    public function matchCancelled(MahjMatch $match): void
    {
        $this->forMatchPlayers(
            $match,
            function (User $player) use ($match): void {
                if ($player->id === $match->host_user_id) {
                    return;
                }

                $this->send(
                    recipient: $player,
                    type: 'match_cancelled',
                    title: 'Game Cancelled',
                    message: $this->matchLabel($match).' was cancelled.',
                    match: $match,
                    eventKey: 'match-cancelled:'.$match->id,
                );
            },
        );
    }

    public function scheduleChanged(MahjMatch $match): void
    {
        $scheduleKey = $match->starts_at?->utc()->format('YmdHis') ?? 'unknown';

        $this->forMatchPlayers(
            $match,
            function (User $player) use ($match, $scheduleKey): void {
                if ($player->id === $match->host_user_id) {
                    return;
                }

                $this->send(
                    recipient: $player,
                    type: 'schedule_changed',
                    title: 'Schedule Changed',
                    message: $this->matchLabel($match).' has a new date or time.',
                    match: $match,
                    eventKey: 'schedule-changed:'.$match->id.':'.$scheduleKey,
                );
            },
        );
    }

    public function chatMessage(
        MahjMatch $match,
        User $sender,
        MatchChatMessage $message,
    ): void {
        $this->forMatchPlayers(
            $match,
            function (User $player) use ($match, $sender, $message): void {
                if ($player->id === $sender->id) {
                    return;
                }

                $this->send(
                    recipient: $player,
                    type: 'chat_message',
                    title: 'New Message',
                    message: $sender->name.' sent a message in '.$this->matchLabel($match).'.',
                    match: $match,
                    relatedUser: $sender,
                    eventKey: 'chat-message:'.$message->id,
                );
            },
        );
    }

    public function gameReminder(MahjMatch $match): void
    {
        $scheduleKey = $match->starts_at?->utc()->format('YmdHis') ?? 'unknown';

        $this->forMatchPlayers(
            $match,
            function (User $player) use ($match, $scheduleKey): void {
                $this->send(
                    recipient: $player,
                    type: 'game_reminder',
                    title: 'Game Reminder',
                    message: $this->matchLabel($match).' starts in about 1 hour.',
                    match: $match,
                    eventKey: 'game-reminder:'.$match->id.':'.$scheduleKey,
                );
            },
        );
    }

    public function subscriptionUpdated(UserSubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if ($subscription->user === null) {
            return;
        }

        $status = str_replace('_', ' ', $subscription->status);
        $fingerprint = sha1(implode('|', [
            (string) $subscription->id,
            (string) $subscription->subscription_plan_id,
            (string) $subscription->status,
            $subscription->cancel_at_period_end ? '1' : '0',
            $subscription->trial_ends_at?->utc()->toISOString() ?? '',
            $subscription->current_period_ends_at?->utc()->toISOString() ?? '',
        ]));

        $this->send(
            recipient: $subscription->user,
            type: 'subscription_update',
            title: 'Subscription Update',
            message: 'Your '.($subscription->plan?->name ?? 'Mahj').' subscription is now '.$status.'.',
            eventKey: 'subscription-update:'.$fingerprint,
        );
    }

    public function send(
        User $recipient,
        string $type,
        string $title,
        string $message,
        ?MahjMatch $match = null,
        ?User $relatedUser = null,
        ?string $eventKey = null,
        array $data = [],
    ): ?UserNotification {
        if (! $this->preferenceEnabled($recipient, $type)) {
            return null;
        }

        $values = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_match_id' => $match?->id,
            'related_user_id' => $relatedUser?->id,
            'data' => $data === [] ? null : $data,
        ];

        if ($eventKey !== null) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $recipient->id,
                    'event_key' => $eventKey,
                ],
                $values,
            );

            if ($notification->wasRecentlyCreated) {
                $this->push->send($recipient, $notification);
            }

            return $notification;
        }

        $notification = UserNotification::query()->create([
            'user_id' => $recipient->id,
            'event_key' => null,
            ...$values,
        ]);

        $this->push->send($recipient, $notification);

        return $notification;
    }

    private function preferenceEnabled(User $user, string $type): bool
    {
        $column = self::PREFERENCE_BY_TYPE[$type] ?? null;
        if ($column === null) {
            return true;
        }

        $preferences = NotificationPreference::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        return (bool) $preferences->{$column};
    }

    private function forMatchPlayers(MahjMatch $match, callable $callback): void
    {
        $match->loadMissing('players');

        foreach ($match->players as $player) {
            $callback($player);
        }
    }

    private function matchLabel(MahjMatch $match): string
    {
        return $match->venue_name
            ?: $match->name
            ?: $match->location_address
            ?: 'Your game';
    }
}

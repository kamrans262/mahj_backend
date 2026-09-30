<?php

namespace App\Services;

use App\Models\MahjMatch;
use App\Models\MatchChatMessage;
use App\Models\User;

class MatchChatService
{
    public function playerJoined(MahjMatch $match, User $user): MatchChatMessage
    {
        return $this->system($match, $user->name.' joined the match');
    }

    public function playerLeft(MahjMatch $match, User $user): MatchChatMessage
    {
        return $this->system($match, $user->name.' left the match');
    }

    public function matchConfirmed(MahjMatch $match): MatchChatMessage
    {
        return $this->system(
            $match,
            'Match confirmed',
            'match-confirmed',
        );
    }

    public function matchCancelled(MahjMatch $match): MatchChatMessage
    {
        return $this->system(
            $match,
            'Match cancelled',
            'match-cancelled',
        );
    }

    public function scheduleChanged(MahjMatch $match): MatchChatMessage
    {
        return $this->system(
            $match,
            'Match schedule changed',
        );
    }

    public function gameReminder(MahjMatch $match): MatchChatMessage
    {
        return $this->system(
            $match,
            'Game reminder: the match starts in about 1 hour',
            'game-reminder-60',
        );
    }

    public function system(
        MahjMatch $match,
        string $body,
        ?string $eventKey = null,
    ): MatchChatMessage {
        $attributes = [
            'match_id' => $match->id,
            'event_key' => $eventKey,
        ];

        $values = [
            'sender_user_id' => null,
            'type' => 'system',
            'body' => $body,
        ];

        if ($eventKey !== null) {
            return MatchChatMessage::query()->firstOrCreate($attributes, $values);
        }

        return MatchChatMessage::query()->create($attributes + $values);
    }
}

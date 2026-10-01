<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'new_games_nearby',
        'game_invitations',
        'players_joining_my_game',
        'game_confirmations',
        'game_reminders',
        'schedule_changes',
        'new_messages',
        'subscription_updates',
    ];

    protected function casts(): array
    {
        return [
            'new_games_nearby' => 'boolean',
            'game_invitations' => 'boolean',
            'players_joining_my_game' => 'boolean',
            'game_confirmations' => 'boolean',
            'game_reminders' => 'boolean',
            'schedule_changes' => 'boolean',
            'new_messages' => 'boolean',
            'subscription_updates' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

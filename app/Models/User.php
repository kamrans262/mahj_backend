<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'zip_code',
        'city',
        'state',
        'bio',
        'avatar_path',
        'email_verified_at',
        'is_admin',
        'is_suspended',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
        ];
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class);
    }

    public function hostedMatches(): HasMany
    {
        return $this->hasMany(MahjMatch::class, 'host_user_id');
    }

    public function joinedMatches(): BelongsToMany
    {
        return $this->belongsToMany(MahjMatch::class, 'match_players', 'user_id', 'match_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function sentMatchInvitations(): HasMany
    {
        return $this->hasMany(MatchInvitation::class, 'inviter_user_id');
    }

    public function receivedMatchInvitations(): HasMany
    {
        return $this->hasMany(MatchInvitation::class, 'invitee_user_id');
    }

    public function matchChatMessages(): HasMany
    {
        return $this->hasMany(MatchChatMessage::class, 'sender_user_id');
    }

    public function submittedUserReports(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reporter_user_id');
    }

    public function receivedUserReports(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reported_user_id');
    }

    public function blockedUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_user_id');
    }

    public function blockedByUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id');
    }
}

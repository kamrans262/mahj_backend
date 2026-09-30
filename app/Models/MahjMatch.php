<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MahjMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'host_user_id',
        'name',
        'location_address',
        'venue_name',
        'starts_at',
        'is_public',
        'is_invite_only',
        'status',
        'max_players',
        'notes',
        'latitude',
        'longitude',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'is_public' => 'boolean',
            'is_invite_only' => 'boolean',
            'max_players' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'cancelled_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'match_players', 'match_id', 'user_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }
}

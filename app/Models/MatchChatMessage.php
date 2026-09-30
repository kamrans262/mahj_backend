<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'sender_user_id',
        'type',
        'event_key',
        'body',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MahjMatch::class, 'match_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}

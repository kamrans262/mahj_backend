<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'direct_conversation_id',
        'sender_user_id',
        'body',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectConversation::class, 'direct_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}

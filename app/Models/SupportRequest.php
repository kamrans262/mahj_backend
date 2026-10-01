<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'topic',
        'message',
        'screenshot_path',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

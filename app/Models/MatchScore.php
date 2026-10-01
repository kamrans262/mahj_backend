<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_user_id',
        'score',
        'submitted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MahjMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}

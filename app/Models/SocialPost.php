<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'author_name',
        'author_handle',
        'text',
        'sentiment',
        'likes',
        'context',
        'match_id',
    ];

    protected $casts = [
        'sentiment' => 'integer',
        'likes' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

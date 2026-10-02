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
        'journalist_id',
        'text',
        'sentiment',
        'likes',
        'context',
        'match_id',
        'manager_reply_key',
        'manager_reply_text',
    ];

    protected $casts = [
        'sentiment' => 'integer',
        'likes' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function journalist(): BelongsTo
    {
        return $this->belongsTo(GameJournalist::class, 'journalist_id');
    }
}

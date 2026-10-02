<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fictional journalist covering the manager's game in the fake social
 * network. Names and handles are 100% invented — no real people, no real
 * media brands.
 */
class GameJournalist extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'name',
        'handle',
        'specialty',
        'followers',
        'active',
    ];

    protected $casts = [
        'followers' => 'integer',
        'active' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class, 'journalist_id');
    }
}

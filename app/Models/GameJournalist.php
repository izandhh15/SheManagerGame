<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A journalist covering the manager's game in the fake social network.
 * For games seeded after the next-gen rebuild, names are verified real
 * women's-football journalists or generic newsroom names (see
 * JournalistService::SOURCES); games seeded before keep their legacy
 * fictional newsroom because seedFor() is idempotent. In-game posts
 * are simulated by the game engine, not real statements by these people.
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

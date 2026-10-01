<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PressStatement extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'match_id',
        'statement_key',
        'target_player_id',
        'sentiment_impact',
    ];

    protected $casts = [
        'sentiment_impact' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

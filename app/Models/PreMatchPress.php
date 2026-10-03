<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreMatchPress extends Model
{
    use HasUuids;

    protected $table = 'pre_match_press';

    protected $fillable = [
        'game_id',
        'match_id',
        'answers',
        'morale_delta',
        'confidence_delta',
        'effects_applied',
    ];

    protected $casts = [
        'answers' => 'array',
        'morale_delta' => 'integer',
        'confidence_delta' => 'integer',
        'effects_applied' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}

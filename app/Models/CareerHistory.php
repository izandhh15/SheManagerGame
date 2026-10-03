<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a deleted game: keeps the career visible in histories even
 * after the save (and its stats rows) are gone.
 */
class CareerHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'career_history';

    protected $fillable = [
        'user_id',
        'game_id',
        'team_name',
        'team_type',
        'season',
        'stats',
        'deleted_at',
    ];

    protected $casts = [
        'stats' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

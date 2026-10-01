<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation from an AI club to the user's team for a pre-season friendly.
 * Optionally a trophy match (e.g. Trofeo Joan Gamper) at a specific stadium.
 *
 * @property string $id
 * @property string $game_id
 * @property string $inviting_team_id
 * @property int $slot
 * @property string|null $trophy_name
 * @property string|null $stadium_name
 * @property string $status pending|accepted|declined
 */
class PreseasonInvitation extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'game_id',
        'inviting_team_id',
        'slot',
        'trophy_name',
        'stadium_name',
        'status',
    ];

    protected $casts = [
        'slot' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function invitingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'inviting_team_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}

<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Negociación de rescisión de contrato de mutuo acuerdo.
 *
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $status
 * @property int $round
 * @property int|null $agent_demand
 * @property int|null $user_offer
 * @property int|null $agreed_amount
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 */
class MutualTerminationNegotiation extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_OPEN = 'open';
    public const STATUS_AGREED = 'agreed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WALKED_AWAY = 'walked_away';
    public const STATUS_COMPLETED = 'completed';

    public const MAX_ROUNDS = 3;

    protected $fillable = [
        'game_id',
        'game_player_id',
        'status',
        'round',
        'agent_demand',
        'user_offer',
        'agreed_amount',
    ];

    protected $casts = [
        'round' => 'integer',
        'agent_demand' => 'integer',
        'user_offer' => 'integer',
        'agreed_amount' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_AGREED,
            self::STATUS_REJECTED,
            self::STATUS_WALKED_AWAY,
            self::STATUS_COMPLETED,
        ]);
    }

    public function getFormattedAgentDemandAttribute(): string
    {
        return Money::format($this->agent_demand);
    }

    public function getFormattedAgreedAmountAttribute(): string
    {
        return Money::format($this->agreed_amount);
    }
}

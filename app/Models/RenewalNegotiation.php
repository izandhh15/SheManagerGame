<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $status
 * @property int $round
 * @property int|null $player_demand
 * @property int|null $preferred_years
 * @property int|null $user_offer
 * @property int|null $offered_years
 * @property int|null $release_clause_requested
 * @property int|null $counter_offer
 * @property int|null $contract_years
 * @property float|null $disposition
 * @property string|null $rival_team_id
 * @property int|null $rival_offer_wage
 * @property int|null $rival_offer_years
 * @property bool $rival_offer_active
 * @property int $agent_patience
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read \App\Models\Team|null $rivalTeam
 * @property-read string $formatted_counter_offer
 * @property-read string $formatted_player_demand
 * @property-read string $formatted_user_offer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereContractYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereCounterOffer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereDisposition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereRivalTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereRivalOfferActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereAgentPatience($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereOfferedYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation wherePlayerDemand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation wherePreferredYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereUserOffer($value)
 * @mixin \Eloquent
 */
class RenewalNegotiation extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_OFFER_PENDING = 'offer_pending';
    public const STATUS_PLAYER_COUNTERED = 'player_countered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PLAYER_REJECTED = 'player_rejected';
    public const STATUS_CLUB_DECLINED = 'club_declined';
    public const STATUS_CLUB_RECONSIDERED = 'club_reconsidered';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'game_id',
        'game_player_id',
        'status',
        'round',
        'player_demand',
        'preferred_years',
        'user_offer',
        'offered_years',
        'release_clause_requested',
        'counter_offer',
        'contract_years',
        'disposition',
        'rejected_at',
        'rival_team_id',
        'rival_offer_wage',
        'rival_offer_years',
        'rival_offer_active',
        'agent_patience',
    ];

    protected $casts = [
        'round' => 'integer',
        'player_demand' => 'integer',
        'preferred_years' => 'integer',
        'user_offer' => 'integer',
        'offered_years' => 'integer',
        'release_clause_requested' => 'integer',
        'counter_offer' => 'integer',
        'contract_years' => 'integer',
        'disposition' => 'float',
        'rejected_at' => 'date',
        'rival_offer_wage' => 'integer',
        'rival_offer_years' => 'integer',
        'rival_offer_active' => 'boolean',
        'agent_patience' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function rivalTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'rival_team_id');
    }

    /**
     * Whether a rival club's offer is actively pressuring this negotiation.
     */
    public function hasActiveRivalOffer(): bool
    {
        return $this->rival_offer_active && $this->rival_team_id !== null;
    }

    /**
     * Patience label for the UI (the agent gets harsher as patience drops).
     */
    public function patienceLevel(): string
    {
        return match (true) {
            $this->agent_patience >= 70 => 'high',
            $this->agent_patience >= 40 => 'medium',
            default => 'low',
        };
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_OFFER_PENDING;
    }

    public function isCountered(): bool
    {
        return $this->status === self::STATUS_PLAYER_COUNTERED;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_PLAYER_REJECTED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OFFER_PENDING, self::STATUS_PLAYER_COUNTERED]);
    }

    public function isBlocking(): bool
    {
        return $this->status === self::STATUS_CLUB_DECLINED;
    }

    /**
     * Check if a renewal negotiation cooldown is active for a player.
     * After a rejected negotiation, the user must wait at least one matchday before retrying.
     */
    public static function hasRenewalCooldown(string $gamePlayerId, $currentDate): bool
    {
        return static::where('game_player_id', $gamePlayerId)
            ->where('status', self::STATUS_PLAYER_REJECTED)
            ->where('rejected_at', '>=', $currentDate)
            ->exists();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACCEPTED,
            self::STATUS_PLAYER_REJECTED,
            self::STATUS_CLUB_DECLINED,
            self::STATUS_CLUB_RECONSIDERED,
            self::STATUS_EXPIRED,
        ]);
    }

    public function getFormattedUserOfferAttribute(): string
    {
        return Money::format($this->user_offer);
    }

    public function getFormattedCounterOfferAttribute(): string
    {
        return Money::format($this->counter_offer);
    }

    public function getFormattedPlayerDemandAttribute(): string
    {
        return Money::format($this->player_demand);
    }
}

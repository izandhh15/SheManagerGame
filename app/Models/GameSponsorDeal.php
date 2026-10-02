<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sponsor deal for the user's club in a game, for the two manager-chosen
 * commercial slots: shirt sponsor (`shirt`) and ad-board sponsor
 * (`ad_board`). The stadium naming-rights slot lives in
 * GameStadiumNamingDeal instead.
 *
 * One table holds the whole lifecycle per slot: periodic offers (`pending`),
 * the accepted contract (`active`), and the trail of expired/rejected rows
 * (history). The sponsor pays a fixed recurring annual fee for as long as
 * the deal runs (see SponsorService), independent of results.
 *
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property string $slot
 * @property string $sponsor_name
 * @property string $tier
 * @property int $annual_value_cents
 * @property int $contract_seasons
 * @property string $status
 * @property bool $is_renewal
 * @property int $offered_season
 * @property \Carbon\Carbon|null $offered_at
 * @property int|null $start_season
 * @property int|null $end_season
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $team
 */
class GameSponsorDeal extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const SLOT_SHIRT = 'shirt';
    public const SLOT_AD_BOARD = 'ad_board';

    public const SLOTS = [
        self::SLOT_SHIRT,
        self::SLOT_AD_BOARD,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    public const TIER_LOCAL = 'local';
    public const TIER_REGIONAL = 'regional';
    public const TIER_NACIONAL = 'nacional';
    public const TIER_INTERNACIONAL = 'internacional';

    protected $fillable = [
        'game_id',
        'team_id',
        'slot',
        'sponsor_name',
        'tier',
        'annual_value_cents',
        'contract_seasons',
        'status',
        'is_renewal',
        'offered_season',
        'offered_at',
        'start_season',
        'end_season',
    ];

    protected $casts = [
        'annual_value_cents' => 'integer',
        'contract_seasons' => 'integer',
        'is_renewal' => 'boolean',
        'offered_season' => 'integer',
        'offered_at' => 'datetime',
        'start_season' => 'integer',
        'end_season' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The single active sponsor deal for a club+slot in a game, if any.
     */
    public static function activeForGameSlot(string $gameId, string $teamId, string $slot): ?self
    {
        return self::query()
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->where('slot', $slot)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Pending offers for a club+slot in a season, richest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function pendingForGameSlot(string $gameId, string $teamId, string $slot, int $season)
    {
        return self::query()
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->where('slot', $slot)
            ->where('status', self::STATUS_PENDING)
            ->where('offered_season', $season)
            ->orderByDesc('annual_value_cents')
            ->get();
    }
}

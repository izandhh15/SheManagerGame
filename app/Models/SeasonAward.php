<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per award of a season's gala (Balón de Oro, Pichichi, Zamora, MVP),
 * as computed from the season's real simulated data by AwardsGalaProcessor.
 *
 * @property string $id
 * @property string $game_id
 * @property string $season
 * @property string $award_key
 * @property string $game_player_id
 * @property string $team_id
 * @property array|null $detail
 */
class SeasonAward extends Model
{
    use HasUuids;

    public const AWARD_BALLON_DOR = 'ballon_dor';
    public const AWARD_PICHICHI = 'pichichi';
    public const AWARD_ZAMORA = 'zamora';
    public const AWARD_MVP = 'mvp';

    public const AWARD_KEYS = [
        self::AWARD_BALLON_DOR,
        self::AWARD_PICHICHI,
        self::AWARD_ZAMORA,
        self::AWARD_MVP,
    ];

    protected $fillable = [
        'game_id',
        'season',
        'award_key',
        'game_player_id',
        'team_id',
        'detail',
    ];

    protected $casts = [
        'detail' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Display name for an award key ("Balón de Oro", "Pichichi"...).
     */
    public static function displayName(string $awardKey): string
    {
        return __("game.gala_award_{$awardKey}");
    }
}

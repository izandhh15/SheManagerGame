<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @property string $id
 * @property string $game_id
 * @property string $season
 * @property array<array-key, mixed> $final_standings
 * @property array<array-key, mixed> $player_season_stats
 * @property array<array-key, mixed> $season_awards
 * @property array<array-key, mixed> $match_results
 * @property array<array-key, mixed>|null $match_events_archive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read array|null $best_goalkeeper
 * @property-read array|null $champion
 * @property-read array|null $most_assists
 * @property-read array|null $top_scorer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereFinalStandings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereMatchResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive wherePlayerSeasonStats($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereSeasonAwards($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SeasonArchive extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'season',
        'final_standings',
        'player_season_stats',
        'season_awards',
        'match_results',
        'match_events_archive',
        'transfer_activity',
        'transition_log',
    ];

    protected $casts = [
        'final_standings' => 'array',
        'player_season_stats' => 'array',
        'season_awards' => 'array',
        'match_results' => 'array',
        'match_events_archive' => 'array',
        'transfer_activity' => 'array',
        'transition_log' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Capture a game's match events as plain arrays for the season
     * archive (M33): who scored, assists, cards, substitutions, injuries.
     * The season-closing processors call this BEFORE purging the
     * `match_events` table, so the per-match detail survives the reset.
     * Player names are denormalised at capture time; the GamePlayer rows
     * persist across seasons but this keeps the archive self-contained.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function captureMatchEvents(string $gameId): array
    {
        $playerNames = GamePlayer::where('game_id', $gameId)->pluck('name', 'id');

        $events = [];

        MatchEvent::where('game_id', $gameId)
            ->orderBy('game_match_id')
            ->orderBy('minute')
            ->chunk(500, function ($chunk) use (&$events, $playerNames) {
                foreach ($chunk as $event) {
                    $events[] = [
                        'game_match_id' => $event->game_match_id,
                        'game_player_id' => $event->game_player_id,
                        'player_name' => $playerNames->get($event->game_player_id),
                        'team_id' => $event->team_id,
                        'minute' => $event->minute,
                        'phase' => $event->phase?->value,
                        'stoppage_minute' => $event->stoppage_minute,
                        'event_type' => $event->event_type,
                        'metadata' => $event->metadata,
                    ];
                }
            });

        return $events;
    }

    /**
     * Get the champion team from awards.
     */
    public function getChampionAttribute(): ?array
    {
        return $this->season_awards['champion'] ?? null;
    }

    /**
     * Get the top scorer from awards.
     */
    public function getTopScorerAttribute(): ?array
    {
        return $this->season_awards['top_scorer'] ?? null;
    }

    /**
     * Get the most assists from awards.
     */
    public function getMostAssistsAttribute(): ?array
    {
        return $this->season_awards['most_assists'] ?? null;
    }

    /**
     * Get the best goalkeeper from awards.
     */
    public function getBestGoalkeeperAttribute(): ?array
    {
        return $this->season_awards['best_goalkeeper'] ?? null;
    }
}

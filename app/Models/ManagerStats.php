<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $game_id
 * @property string|null $team_id
 * @property string|null $game_mode
 * @property int $matches_played
 * @property int $matches_won
 * @property int $matches_drawn
 * @property int $matches_lost
 * @property float $win_percentage
 * @property int $current_unbeaten_streak
 * @property int $longest_unbeaten_streak
 * @property int $seasons_completed
 * @property int $trophies_count
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Game|null $game
 * @property-read \App\Models\Team|null $team
 */
class ManagerStats extends Model
{
    use HasUuids;

    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'game_id',
        'team_id',
        'game_mode',
        'matches_played',
        'matches_won',
        'matches_drawn',
        'matches_lost',
        'win_percentage',
        'current_unbeaten_streak',
        'longest_unbeaten_streak',
        'seasons_completed',
        'trophies_count',
    ];

    protected $casts = [
        'matches_played' => 'integer',
        'matches_won' => 'integer',
        'matches_drawn' => 'integer',
        'matches_lost' => 'integer',
        'win_percentage' => 'decimal:2',
        'current_unbeaten_streak' => 'integer',
        'longest_unbeaten_streak' => 'integer',
        'seasons_completed' => 'integer',
        'trophies_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Recalculate win percentage from current W/D/L counts.
     */
    public function recalculateWinPercentage(): void
    {
        $this->win_percentage = $this->matches_played > 0
            ? round(($this->matches_won / $this->matches_played) * 100, 2)
            : 0;
    }

    /**
     * Record a match result and update all derived stats.
     *
     * SIN transacción ni lock (25-10-2026, Neon pooler): la
     * transacción con la fila bloqueada abortaba en silencio y
     * /finalize-match daba 500 tras CADA partido de carrera (el
     * listener UpdateManagerStats corre dentro de los efectos
     * posteriores al partido, después de las clasificaciones, así
     * que el 500 llegaba con todo lo demás ya aplicado). Los
     * contadores usan incrementos atómicos (un solo UPDATE cada
     * uno) y la racha máxima se actualiza con GREATEST en una
     * sola sentencia; el finalize con claim atómico ya serializa
     * las finalizaciones por partida, así que no hay doble
     * conteo por doble clic.
     */
    public function recordResult(string $result): void
    {
        $column = match ($result) {
            'win' => 'matches_won',
            'draw' => 'matches_drawn',
            'loss' => 'matches_lost',
        };

        static::whereKey($this->getKey())->increment('matches_played');
        static::whereKey($this->getKey())->increment($column);

        if ($result === 'loss') {
            static::whereKey($this->getKey())->update(['current_unbeaten_streak' => 0]);
        } else {
            static::whereKey($this->getKey())->increment('current_unbeaten_streak');
            static::whereKey($this->getKey())->update([
                'longest_unbeaten_streak' => DB::raw('GREATEST(longest_unbeaten_streak, current_unbeaten_streak)'),
            ]);
        }

        $fresh = static::find($this->getKey());

        if ($fresh) {
            $fresh->recalculateWinPercentage();
            $fresh->save();
            $this->setRawAttributes($fresh->getAttributes());
        }
    }
}

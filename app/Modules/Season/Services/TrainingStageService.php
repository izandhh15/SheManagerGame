<?php

namespace App\Modules\Season\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Training stage (concentración) for national-team games.
 *
 * The manager configures destination, duration, intensity and focus. Each
 * option has a cost (charged against the federation budget) and effects
 * applied to the squad when the stage is confirmed:
 *
 * - Intensity suave/equilibrada/intensa → fitness & morale deltas, injury risk.
 * - Focus físico → extra fitness; táctico → extra morale; cantera → young
 *   players (≤21) gain overall.
 * - Duration multiplies both cost and effects.
 *
 * A game can organize one stage; confirming stores the config on the game.
 */
class TrainingStageService
{
    public const DURATIONS = [
        '1w' => ['weeks' => 1, 'cost_mult' => 1.0, 'effect_mult' => 1.0],
        '2w' => ['weeks' => 2, 'cost_mult' => 1.8, 'effect_mult' => 1.7],
    ];

    public const INTENSITIES = [
        'light' => ['fitness' => 3, 'morale' => 5, 'injury_risk' => 1, 'cost_mult' => 1.0],
        'balanced' => ['fitness' => 7, 'morale' => 3, 'injury_risk' => 3, 'cost_mult' => 1.25],
        'intense' => ['fitness' => 12, 'morale' => -2, 'injury_risk' => 8, 'cost_mult' => 1.6],
    ];

    public const FOCUSES = [
        'physical' => ['fitness' => 4, 'morale' => 0, 'injury_risk' => 2, 'cost_mult' => 1.0, 'youth_boost' => 0],
        'tactical' => ['fitness' => 0, 'morale' => 6, 'injury_risk' => 0, 'cost_mult' => 1.0, 'youth_boost' => 0],
        'youth' => ['fitness' => 0, 'morale' => 4, 'injury_risk' => 0, 'cost_mult' => 1.2, 'youth_boost' => 2],
    ];

    public const BASE_WEEKLY_COST_HOME = 60000;
    public const BASE_WEEKLY_COST_ABROAD = 100000;

    public const YOUTH_MAX_AGE = 21;

    /**
     * Cost in euros for a stage configuration.
     */
    public function calculateCost(string $destinationCountry, string $userCountry, string $duration, string $intensity, string $focus): int
    {
        $weekly = strcasecmp(trim($destinationCountry), trim($userCountry)) === 0
            ? self::BASE_WEEKLY_COST_HOME
            : self::BASE_WEEKLY_COST_ABROAD;

        $weeks = self::DURATIONS[$duration]['weeks'] ?? 1;
        $cost = $weekly * $weeks;
        $cost *= self::DURATIONS[$duration]['cost_mult'] ?? 1.0;
        $cost *= self::INTENSITIES[$intensity]['cost_mult'] ?? 1.0;
        $cost *= self::FOCUSES[$focus]['cost_mult'] ?? 1.0;

        return (int) round($cost);
    }

    /**
     * Combined effects (fitness/morale deltas, injury risk %, youth boost)
     * for a stage configuration. Used for the pre-confirm summary.
     *
     * @return array{fitness:int, morale:int, injury_risk:int, youth_boost:int}
     */
    public function calculateEffects(string $duration, string $intensity, string $focus): array
    {
        $mult = self::DURATIONS[$duration]['effect_mult'] ?? 1.0;
        $i = self::INTENSITIES[$intensity] ?? self::INTENSITIES['balanced'];
        $f = self::FOCUSES[$focus] ?? self::FOCUSES['physical'];

        return [
            'fitness' => (int) round(($i['fitness'] + $f['fitness']) * $mult),
            'morale' => (int) round(($i['morale'] + $f['morale']) * $mult),
            'injury_risk' => $i['injury_risk'] + $f['injury_risk'],
            'youth_boost' => $f['youth_boost'],
        ];
    }

    /**
     * Confirm a club preseason stage: charge the club's transfer budget,
     * apply squad effects, and persist the configuration on the game.
     * Clubs may organize one stage per season.
     *
     * @return array{ok:bool, message:string, cost?:int, effects?:array, injured?:array}
     */
    public function confirmClubStage(Game $game, array $config): array
    {
        if ($game->isTournamentMode()) {
            return ['ok' => false, 'message' => __('game.stage_club_not_available')];
        }

        if (! $this->validateConfig($config)) {
            return ['ok' => false, 'message' => __('game.stage_invalid_config')];
        }

        $existing = $game->training_stage;
        if (is_array($existing) && ($existing['season'] ?? null) === $game->season) {
            return ['ok' => false, 'message' => __('game.stage_already_organized')];
        }

        $userTeam = $game->team;
        $userCountry = \App\Support\CountryNames::name($userTeam->country) ?? $userTeam->country ?? '';

        $cost = $this->calculateCost(
            $config['destination'], $userCountry,
            $config['duration'], $config['intensity'], $config['focus']
        );

        // 32-bit safe: stage costs are < €1M, ×100 cents stays far below 2^31.
        // NB: $game->refresh() can leave this relation eager-loaded as null
        // (the model docblock warns against eager-loading it), so drop any
        // cached copy and lazy-load it fresh.
        $game->unsetRelation('currentInvestment');
        $investment = $game->currentInvestment;
        if ($investment === null || (int) $investment->transfer_budget < $cost * 100) {
            return ['ok' => false, 'message' => __('game.stage_club_not_enough_budget')];
        }

        $effects = $this->calculateEffects($config['duration'], $config['intensity'], $config['focus']);

        DB::transaction(function () use ($game, $investment, $config, $cost, $effects) {
            $investment->decrement('transfer_budget', $cost * 100);
            $game->update([
                'training_stage' => array_merge($config, [
                    'cost' => $cost,
                    'effects' => $effects,
                    'season' => $game->season,
                    'organized_at' => Carbon::now()->toDateTimeString(),
                ]),
            ]);

            $this->applySquadEffects($game, $effects);
        });

        $game->refresh();

        $injuredNames = collect($game->training_stage['injured'] ?? [])->pluck('name')->all();

        return [
            'ok' => true,
            'message' => __('game.stage_confirmed'),
            'cost' => $cost,
            'effects' => $effects,
            'injured' => $injuredNames,
        ];
    }

    /**
     * Validate a stage configuration array.
     */
    public function validateConfig(array $config): bool
    {
        return isset(self::DURATIONS[$config['duration'] ?? ''])
            && isset(self::INTENSITIES[$config['intensity'] ?? ''])
            && isset(self::FOCUSES[$config['focus'] ?? ''])
            && is_string($config['destination'] ?? null)
            && trim($config['destination']) !== '';
    }

    /**
     * Confirm a stage: charge the federation budget, apply squad effects,
     * and persist the configuration on the game. Returns a result summary.
     *
     * @return array{ok:bool, message:string, cost?:int, effects?:array, injured?:array}
     */
    public function confirmStage(Game $game, array $config): array
    {
        if (! $this->validateConfig($config)) {
            return ['ok' => false, 'message' => __('game.stage_invalid_config')];
        }

        if ($game->training_stage) {
            return ['ok' => false, 'message' => __('game.stage_already_organized')];
        }

        $userTeam = $game->team;
        $userCountry = $userTeam->country ?? '';

        $cost = $this->calculateCost(
            $config['destination'], $userCountry,
            $config['duration'], $config['intensity'], $config['focus']
        );

        if (($game->federation_budget ?? 0) < $cost) {
            return ['ok' => false, 'message' => __('game.stage_not_enough_budget')];
        }

        $effects = $this->calculateEffects($config['duration'], $config['intensity'], $config['focus']);

        DB::transaction(function () use ($game, $config, $cost, $effects) {
            $game->update([
                'federation_budget' => $game->federation_budget - $cost,
                'training_stage' => array_merge($config, [
                    'cost' => $cost,
                    'effects' => $effects,
                    'organized_at' => Carbon::now()->toDateTimeString(),
                ]),
            ]);

            $this->applySquadEffects($game, $effects);
        });

        $game->refresh();

        $injuredNames = collect($game->training_stage['injured'] ?? [])->pluck('name')->all();

        return [
            'ok' => true,
            'message' => __('game.stage_confirmed'),
            'cost' => $cost,
            'effects' => $effects,
            'injured' => $injuredNames,
        ];
    }

    /**
     * Apply fitness/morale/injury/youth effects to the user's squad.
     */
    private function applySquadEffects(Game $game, array $effects): void
    {
        $playerIds = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->pluck('id')
            ->all();

        if (empty($playerIds)) {
            return;
        }

        // Fitness & morale (clamped 0–100).
        $fitness = (int) $effects['fitness'];
        $morale = (int) $effects['morale'];
        if ($fitness !== 0 || $morale !== 0) {
            $idList = "'" . implode("','", $playerIds) . "'";
            $sets = [];
            if ($fitness !== 0) {
                $sets[] = "fitness = LEAST(100, GREATEST(0, fitness + {$fitness}))";
            }
            if ($morale !== 0) {
                $sets[] = "morale = LEAST(100, GREATEST(0, morale + {$morale}))";
            }
            DB::statement('UPDATE game_player_match_state SET ' . implode(', ', $sets) . " WHERE game_player_id IN ({$idList})");
        }

        // Injury rolls.
        $risk = (int) $effects['injury_risk'];
        $injured = [];
        if ($risk > 0) {
            $injuryTypes = ['Muscle fatigue', 'Muscle strain', 'Calf strain', 'Groin strain'];
            $baseDate = $game->current_date ? Carbon::parse($game->current_date) : Carbon::now();
            $injuries = [];
            foreach ($playerIds as $playerId) {
                if (random_int(1, 100) <= $risk) {
                    $days = random_int(7, 21);
                    $injuries[] = [
                        'playerId' => $playerId,
                        'injuryType' => $injuryTypes[array_rand($injuryTypes)],
                        'injuryUntil' => $baseDate->copy()->addDays($days),
                    ];
                }
            }
            if (! empty($injuries)) {
                GamePlayerMatchState::bulkSetInjuries($injuries);
                $names = GamePlayer::whereIn('id', array_column($injuries, 'playerId'))->pluck('name', 'id')->all();
                foreach ($injuries as $inj) {
                    $injured[] = ['name' => $names[$inj['playerId']] ?? '?', 'until' => $inj['injuryUntil']->toDateString()];
                }
            }
        }

        // Youth focus: overall boost for players aged ≤ 21.
        $youthBoost = (int) ($effects['youth_boost'] ?? 0);
        if ($youthBoost > 0) {
            $cutoff = Carbon::now()->subYears(self::YOUTH_MAX_AGE + 1)->toDateString();
            DB::table('game_players')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('date_of_birth', '>', $cutoff)
                ->update(['overall_score' => DB::raw("LEAST(99, overall_score + {$youthBoost})")]);
        }

        // Persist the injury list inside the stored stage config.
        $stage = $game->training_stage ?? [];
        $stage['injured'] = $injured;
        $game->update(['training_stage' => $stage]);
    }

    /**
     * Human-readable summary lines for the pre-confirm panel.
     *
     * @return list<string>
     */
    public function effectSummaryLines(array $effects): array
    {
        $lines = [];
        $fmt = fn (int $v) => ($v >= 0 ? '+' : '') . $v;

        $lines[] = __('game.stage_effect_fitness', ['value' => $fmt((int) ($effects['fitness'] ?? 0))]);
        $lines[] = __('game.stage_effect_morale', ['value' => $fmt((int) ($effects['morale'] ?? 0))]);
        $lines[] = __('game.stage_effect_injury', ['risk' => (int) ($effects['injury_risk'] ?? 0)]);
        if ((int) ($effects['youth_boost'] ?? 0) > 0) {
            $lines[] = __('game.stage_effect_youth', ['boost' => '+' . (int) $effects['youth_boost']]);
        }

        return $lines;
    }
}

<?php

namespace App\Modules\Season\Services;

use App\Models\ClubProfile;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\TeamReputation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Preseason tours (giras de pretemporada) for club-mode games.
 *
 * Before confirming the pre-season friendlies, the manager can take the
 * club on tour to a destination (USA, México, Inglaterra, Alemania...).
 * The tour costs money up front (travel + logistics, charged against the
 * club's transfer budget), and in return every home pre-season friendly
 * earns multiplied matchday revenue (tour ticket sales + tour sponsors),
 * while the club gains a small chunk of reputation points.
 *
 * One tour per game; it must be organized before the friendlies are
 * confirmed. The automatic "derbi de la casa" stays a home-lab match and
 * is NOT boosted by the tour.
 */
class PreseasonTourService
{
    /**
     * @var array<string, array{country_code:string, cost:int, revenue_multiplier:float, prestige_points:int}>
     * cost in euros; prestige_points added to the club's team_reputations row.
     */
    public const DESTINATIONS = [
        'usa' => ['country_code' => 'US', 'cost' => 350_000, 'revenue_multiplier' => 2.2, 'prestige_points' => 60],
        'mexico' => ['country_code' => 'MX', 'cost' => 250_000, 'revenue_multiplier' => 2.0, 'prestige_points' => 45],
        'england' => ['country_code' => 'EN', 'cost' => 200_000, 'revenue_multiplier' => 1.8, 'prestige_points' => 35],
        'germany' => ['country_code' => 'DE', 'cost' => 180_000, 'revenue_multiplier' => 1.7, 'prestige_points' => 30],
    ];

    public const EXPENSE_CATEGORY = FinancialTransaction::CATEGORY_TOUR;

    /**
     * Organize the tour: charge the cost, add prestige points and persist
     * the tour config on the game.
     *
     * @return array{ok:bool, message:string, destination?:string, cost?:int}
     */
    public function organize(Game $game, string $destination): array
    {
        $key = strtolower(trim($destination));

        if ($game->isTournamentMode() || ! $game->needsPreseasonOpponentSelection()) {
            return ['ok' => false, 'message' => __('game.preseason_tour_not_available')];
        }

        if (! isset(self::DESTINATIONS[$key])) {
            return ['ok' => false, 'message' => __('game.preseason_tour_invalid_destination')];
        }

        if ($game->preseason_tour) {
            return ['ok' => false, 'message' => __('game.preseason_tour_already_organized')];
        }

        $config = self::DESTINATIONS[$key];
        $costEuros = $config['cost'];

        $investment = $game->currentInvestment;
        if ($investment === null || (int) $investment->transfer_budget < $costEuros * 100) {
            return ['ok' => false, 'message' => __('game.preseason_tour_no_budget')];
        }

        DB::transaction(function () use ($game, $investment, $key, $config, $costEuros) {
            $investment->decrement('transfer_budget', $costEuros * 100);

            FinancialTransaction::recordExpense(
                gameId: $game->id,
                category: self::EXPENSE_CATEGORY,
                amount: $costEuros * 100,
                description: __('game.preseason_tour_expense_desc', [
                    'destination' => $this->destinationName($key),
                ]),
                transactionDate: ($game->current_date ?? Carbon::now())->toDateString(),
            );

            $this->awardPrestigePoints($game, $config['prestige_points']);

            $game->update([
                'preseason_tour' => [
                    'destination' => $key,
                    'cost' => $costEuros,
                    'revenue_multiplier' => $config['revenue_multiplier'],
                    'prestige_points' => $config['prestige_points'],
                    'organized_at' => Carbon::now()->toDateTimeString(),
                ],
            ]);
        });

        // NB: currentInvestment must stay lazy-loaded (its where() closes
        // over the parent instance's season, which eager loading breaks).
        $game->unsetRelation('currentInvestment');
        $game->refresh();

        return [
            'ok' => true,
            'message' => __('game.preseason_tour_organized', [
                'destination' => $this->destinationName($key),
            ]),
            'destination' => $key,
            'cost' => $costEuros,
        ];
    }

    /**
     * The revenue multiplier for a home friendly when it belongs to an
     * organized tour, 1.0 otherwise.
     */
    public function tourRevenueMultiplier(Game $game, GameMatch $match): float
    {
        $tour = $game->preseason_tour;

        if (empty($tour['revenue_multiplier'])) {
            return 1.0;
        }

        if ($match->competition_id !== PreseasonOpponentService::PRESEASON_COMPETITION_ID) {
            return 1.0;
        }

        // The family derby is a home-lab match against the filial, not a
        // tour commercial date.
        if ((int) $match->round_number === PreseasonOpponentService::FAMILY_DERBY_ROUND_NUMBER) {
            return 1.0;
        }

        return (float) $tour['revenue_multiplier'];
    }

    /**
     * Localized destination names for the picker.
     *
     * @return list<array{key:string, name:string, cost:int, revenue_multiplier:float, prestige_points:int}>
     */
    public function destinationOptions(): array
    {
        $options = [];
        foreach (self::DESTINATIONS as $key => $config) {
            $options[] = [
                'key' => $key,
                'name' => $this->destinationName($key),
                'cost' => $config['cost'],
                'revenue_multiplier' => $config['revenue_multiplier'],
                'prestige_points' => $config['prestige_points'],
            ];
        }

        return $options;
    }

    public function destinationName(string $key): string
    {
        return __('game.preseason_tour_dest_'.strtolower(trim($key)));
    }

    /**
     * Add reputation points to the club's game-scoped row (creating it from
     * the club profile when missing) and recalculate its tier.
     */
    private function awardPrestigePoints(Game $game, int $points): void
    {
        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if ($reputation === null) {
            $base = ClubProfile::where('team_id', $game->team_id)->value('reputation_level')
                ?? ClubProfile::REPUTATION_LOCAL;

            $reputation = TeamReputation::create([
                'game_id' => $game->id,
                'team_id' => $game->team_id,
                'reputation_level' => $base,
                'base_reputation_level' => $base,
                'reputation_points' => 0,
            ]);
        }

        $reputation->increment('reputation_points', $points);
        $reputation->refresh();
        $reputation->recalculateTier();
        $reputation->save();

        TeamReputation::flushCacheFor($game->id, $game->team_id);
    }
}

<?php

namespace App\Modules\Season\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\DTOs\AffiliateSackDecision;

/**
 * Season-end evaluation for affiliate careers ("Carrera con Filiales").
 *
 * The user manages ONLY the reserve side; the first team is AI-managed with
 * its own named coach (Team::manager_name). At the season rollover this
 * service checks how the first team did:
 *
 *   - relegated, or finished inside the relegation zone, or
 *   - finished further than OBJECTIVE_MISS_MARGIN positions below the
 *     board's season objective (derived from the club's reputation, same
 *     as a human manager's season goal)
 *
 * …then the board sacks the coach and hands the first team to the user.
 * Otherwise the user stays another season with the reserve side.
 *
 * Runs inside the season-closing pipeline (after promotion/relegation has
 * been applied and every league has final standings), because the first
 * team's league is only fully simulated at season end.
 */
class AffiliateFirstTeamSackService
{
    /**
     * Positions worse than target + this margin count as "far from the
     * board's objective".
     */
    private const OBJECTIVE_MISS_MARGIN = 5;

    public function __construct(
        private readonly CountryConfig $countryConfig,
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    /**
     * Evaluate the first team at season end. Returns a sack decision when
     * the board fires its coach, null when the user stays with the filial.
     */
    public function evaluate(Game $game): ?AffiliateSackDecision
    {
        if ($game->pair_mode !== 'affiliate') {
            return null;
        }

        // Legacy two-save affiliate pairs (with a linked partner) keep the
        // old behaviour; only the new single-save careers use the sack rule.
        if ($game->dualPartner() !== null) {
            return null;
        }

        $reserve = Team::find($game->team_id);
        if (! $reserve || $reserve->parent_team_id === null) {
            // Already managing the first team (post-promotion) or invalid.
            return null;
        }

        $parent = Team::find($reserve->parent_team_id);
        if (! $parent) {
            return null;
        }

        // Final standing of the season that just ended: during the closing
        // pipeline the standings table still holds the old season. Prefer
        // the domestic league (a club in a UEFA league phase has two
        // league-role standings rows).
        $standing = GameStanding::where('game_id', $game->id)
            ->where('team_id', $parent->id)
            ->whereHas('competition', fn ($q) => $q->where('role', Competition::ROLE_LEAGUE))
            ->with('competition')
            ->get()
            ->sortByDesc(fn ($s) => $s->competition->scope === Competition::SCOPE_DOMESTIC ? 1 : 0)
            ->first();

        if (! $standing || $standing->position === null) {
            return null;
        }

        $league = $standing->competition;
        $position = (int) $standing->position;

        $relegatedPositions = $this->relegatedPositions($game->country, $league->id);
        $inRelegationZone = in_array($position, $relegatedPositions, true);
        $relegated = $this->wasRelegated($game, $parent, $league);

        $goal = $this->seasonGoalService->determineGoalForTeam($parent, $league, $game);
        $target = $this->seasonGoalService->getTargetPosition($goal, $league);
        $farFromObjective = $position > $target + self::OBJECTIVE_MISS_MARGIN;

        if (! $inRelegationZone && ! $relegated && ! $farFromObjective) {
            return null;
        }

        $reason = $relegated
            ? AffiliateSackDecision::REASON_RELEGATED
            : ($inRelegationZone
                ? AffiliateSackDecision::REASON_RELEGATION_ZONE
                : AffiliateSackDecision::REASON_MISSED_OBJECTIVE);

        return new AffiliateSackDecision(
            parentTeam: $parent,
            reserveTeam: $reserve,
            coachName: $parent->manager_name,
            finalPosition: $position,
            boardTargetPosition: $target,
            boardGoal: $goal,
            reason: $reason,
            newLeagueId: $this->resolveNewLeagueId($game, $parent, $league->id),
        );
    }

    /**
     * Positions that relegate from the given competition, per the country
     * promotion/relegation rules.
     *
     * @return int[]
     */
    private function relegatedPositions(string $country, string $competitionId): array
    {
        foreach ($this->countryConfig->promotions($country) as $rule) {
            if (($rule['top_division'] ?? null) === $competitionId) {
                return $rule['relegated_positions'] ?? [];
            }
        }

        return [];
    }

    /**
     * Whether the promotion/relegation pass moved the first team down a tier.
     */
    private function wasRelegated(Game $game, Team $parent, Competition $oldLeague): bool
    {
        $newLeagueId = $this->resolveNewLeagueId($game, $parent, $oldLeague->id);

        if ($newLeagueId === $oldLeague->id) {
            return false;
        }

        $newTier = Competition::where('id', $newLeagueId)->value('tier');

        return $newTier !== null && $oldLeague->tier !== null && $newTier > $oldLeague->tier;
    }

    /**
     * League competition the first team is entered in for the new season
     * (post promotion/relegation), falling back to the old league.
     */
    private function resolveNewLeagueId(Game $game, Team $parent, string $fallbackLeagueId): string
    {
        $leagueIds = Competition::where('role', Competition::ROLE_LEAGUE)->pluck('id')->all();

        if ($leagueIds === []) {
            return $fallbackLeagueId;
        }

        return CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $parent->id)
            ->whereIn('competition_id', $leagueIds)
            ->value('competition_id')
            ?? $fallbackLeagueId;
    }
}

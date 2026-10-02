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

    /**
     * Mid-season sack: the board only pulls the trigger once the season is
     * under way (at least this many league matches played)…
     */
    public const MIDSEASON_MIN_PLAYED = 8;

    /**
     * …and the bar is higher than at season end, so it doesn't fire every
     * season: relegation zone, or further than this many positions below the
     * board's objective.
     */
    private const MIDSEASON_OBJECTIVE_MISS_MARGIN = 8;

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
        $context = $this->affiliateContext($game);
        if (! $context) {
            return null;
        }
        [$reserve, $parent] = $context;

        $standing = $this->parentDomesticStanding($game, $parent);

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
     * Mid-season evaluation, run after every simulated matchday once the
     * season is under way. Same sack rule as the season-end check but with a
     * higher bar (relegation zone or clearly adrift of the board's objective
     * after at least MIDSEASON_MIN_PLAYED matches), so the board only acts
     * when the situation is genuinely dramatic.
     *
     * Returns a sack decision when the board fires the coach mid-season and
     * hands the first team to the user "para salvar al equipo", null
     * otherwise. Never fires twice: the game is flagged on the first sack.
     */
    public function evaluateMidSeason(Game $game): ?AffiliateSackDecision
    {
        $context = $this->affiliateContext($game);
        if (! $context) {
            return null;
        }
        [$reserve, $parent] = $context;

        $standing = $this->parentDomesticStanding($game, $parent);

        if (! $standing || $standing->position === null) {
            return null;
        }

        // Season still too young: the board waits before judging.
        if ((int) ($standing->played ?? 0) < self::MIDSEASON_MIN_PLAYED) {
            return null;
        }

        $league = $standing->competition;
        $position = (int) $standing->position;

        $relegatedPositions = $this->relegatedPositions($game->country, $league->id);
        $inRelegationZone = in_array($position, $relegatedPositions, true);

        $goal = $this->seasonGoalService->determineGoalForTeam($parent, $league, $game);
        $target = $this->seasonGoalService->getTargetPosition($goal, $league);
        $farFromObjective = $position > $target + self::MIDSEASON_OBJECTIVE_MISS_MARGIN;

        if (! $inRelegationZone && ! $farFromObjective) {
            return null;
        }

        return new AffiliateSackDecision(
            parentTeam: $parent,
            reserveTeam: $reserve,
            coachName: $parent->manager_name,
            finalPosition: $position,
            boardTargetPosition: $target,
            boardGoal: $goal,
            reason: $inRelegationZone
                ? AffiliateSackDecision::REASON_RELEGATION_ZONE
                : AffiliateSackDecision::REASON_MISSED_OBJECTIVE,
            // Mid-season there is no "next season" yet: the user takes over
            // the first team in its current league.
            newLeagueId: $league->id,
        );
    }

    /**
     * Shared guards for both evaluations. Returns [reserve, parent] when the
     * save is an affiliate career where the user still manages the reserve
     * side and no mid-season sack has fired yet, null otherwise.
     *
     * @return array{Team, Team}|null
     */
    private function affiliateContext(Game $game): ?array
    {
        if ($game->pair_mode !== 'affiliate') {
            return null;
        }

        // A mid-season sack already happened on this save: never evaluate again.
        if ($game->affiliate_midseason_sack) {
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

        return [$reserve, $parent];
    }

    /**
     * The first team's current domestic-league standing. During the season
     * the standings table holds the live table; at the closing pipeline it
     * holds the final one. Prefer the domestic league (a club in a UEFA
     * league phase has two league-role standings rows).
     */
    private function parentDomesticStanding(Game $game, Team $parent): ?GameStanding
    {
        return GameStanding::where('game_id', $game->id)
            ->where('team_id', $parent->id)
            ->whereHas('competition', fn ($q) => $q->where('role', Competition::ROLE_LEAGUE))
            ->with('competition')
            ->get()
            ->sortByDesc(fn ($s) => $s->competition->scope === Competition::SCOPE_DOMESTIC ? 1 : 0)
            ->first();
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

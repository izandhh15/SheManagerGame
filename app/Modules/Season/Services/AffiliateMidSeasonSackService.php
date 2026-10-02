<?php

namespace App\Modules\Season\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GameStanding;
use App\Models\ManagerStats;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mid-season sack for affiliate careers ("Carrera con Filiales").
 *
 * The user manages only the reserve side while the first team is AI-managed.
 * After every simulated matchday, once the season is under way, the board
 * may sack the first-team coach if the team is in the relegation zone or
 * clearly adrift of the board's objective — and hand the job to the user
 * "para salvar al equipo" (te cuelgan el marrón).
 *
 * The team switch mirrors the season-end AffiliateFirstTeamSackProcessor:
 * the user takes over the first team in its current league, the filial link
 * is kept so call-ups/send-backs work like a normal first-team career, and
 * the game is flagged so the season-end evaluation never fires a second time.
 */
class AffiliateMidSeasonSackService
{
    public function __construct(
        private readonly AffiliateFirstTeamSackService $sackService,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Evaluate and, when the board pulls the trigger, apply the mid-season
     * takeover. Returns true when the sack fired.
     */
    public function trigger(Game $game): bool
    {
        $decision = $this->sackService->evaluateMidSeason($game);

        if (! $decision) {
            return false;
        }

        $parent = $decision->parentTeam;
        $reserve = $decision->reserveTeam;
        $league = Competition::find($decision->newLeagueId);

        DB::transaction(function () use ($game, $decision, $parent, $reserve, $league) {
            $game->update([
                'team_id' => $parent->id,
                // From now on the filial link works exactly like a normal
                // first-team career: call-ups and send-backs between the
                // first team and the reserve.
                'reserve_team_id' => $reserve->id,
                // Mid-season: the first team's current league, whose fixtures
                // are already generated and partially played.
                'competition_id' => $decision->newLeagueId,
                // Fresh board objective for the rescue job, derived from the
                // club's reputation like any other season goal.
                'season_goal' => $league
                    ? $this->seasonGoalService->determineGoalForTeam($parent, $league, $game)
                    : null,
                // The season-end sack evaluation must never fire again.
                'affiliate_midseason_sack' => true,
            ]);

            // Keep the leaderboard aggregate pointing at the manager's
            // current club. No-op if the row doesn't exist yet.
            ManagerStats::where('game_id', $game->id)
                ->update(['team_id' => $parent->id]);
        });

        $coach = $decision->coachName ?? __('game.affiliate_generic_coach');

        $this->notifications->create(
            $game->fresh(),
            'affiliate_midseason_sack',
            __('game.affiliate_midseason_sack_title', ['club' => $parent->name]),
            __('game.affiliate_midseason_sack_message', [
                'club' => $parent->name,
                'coach' => $coach,
                'position' => $decision->finalPosition,
                'matchday' => $this->playedMatchdays($game, $parent->id, $decision->newLeagueId),
                'reason' => __('game.affiliate_sack_reason_' . $decision->reason),
            ]),
            GameNotification::PRIORITY_MILESTONE,
        );

        Log::info('[AffiliateMidSeasonSack] board sacked first-team coach mid-season; user takes over', [
            'game_id' => $game->id,
            'club' => $parent->name,
            'coach' => $decision->coachName,
            'reason' => $decision->reason,
            'position' => $decision->finalPosition,
            'board_target' => $decision->boardTargetPosition,
        ]);

        return true;
    }

    private function playedMatchdays(Game $game, string $teamId, string $leagueId): int
    {
        return (int) GameStanding::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->where('competition_id', $leagueId)
            ->value('played');
    }
}

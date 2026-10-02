<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\ManagerStats;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\AffiliateFirstTeamSackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closing-pipeline processor for affiliate careers ("Carrera con Filiales").
 *
 * The user manages only the reserve side; the first team is AI-managed.
 * At the season rollover, if the first team was relegated, finished in the
 * relegation zone, or ended far below the board's objective, the board
 * sacks its coach and hands the first team to the user.
 *
 * Runs LAST in the closing pipeline (priority 101, after
 * UefaQualificationProcessor): every closing step (trophies, records,
 * settlements) must still run against the reserve side the user actually
 * managed that season. The team switch then happens before the season
 * flips, and the setup pipeline builds the new season around the first
 * team's league via the mutated transition DTO.
 */
class AffiliateFirstTeamSackProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly AffiliateFirstTeamSackService $sackService,
        private readonly NotificationService $notifications,
    ) {}

    public function priority(): int
    {
        return 101;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $decision = $this->sackService->evaluate($game);

        if (! $decision) {
            return $data;
        }

        $parent = $decision->parentTeam;
        $reserve = $decision->reserveTeam;

        DB::transaction(function () use ($game, $decision, $parent, $reserve) {
            $game->update([
                'team_id' => $parent->id,
                // From now on the filial link works exactly like a normal
                // first-team career: call-ups and send-backs between the
                // first team and the reserve.
                'reserve_team_id' => $reserve->id,
                'competition_id' => $decision->newLeagueId,
                // Re-derived for the new club by the setup processors.
                'season_goal' => null,
            ]);

            // Keep the leaderboard aggregate pointing at the manager's
            // current club. No-op if the row doesn't exist yet.
            ManagerStats::where('game_id', $game->id)
                ->update(['team_id' => $parent->id]);
        });

        // The setup pipeline must generate fixtures/standings/cups for the
        // first team's league, not the reserve's.
        $data->competitionId = $decision->newLeagueId;

        $coach = $decision->coachName ?? __('game.affiliate_generic_coach');

        $this->notifications->create(
            $game->fresh(),
            'affiliate_first_team_promotion',
            __('game.affiliate_sack_title', ['club' => $parent->name]),
            __('game.affiliate_sack_message', [
                'club' => $parent->name,
                'coach' => $coach,
                'position' => $decision->finalPosition,
                'reason' => __('game.affiliate_sack_reason_' . $decision->reason),
            ]),
            GameNotification::PRIORITY_MILESTONE,
        );

        Log::info('[AffiliateFirstTeamSack] board sacked first-team coach; user promoted', [
            'game_id' => $game->id,
            'club' => $parent->name,
            'coach' => $decision->coachName,
            'reason' => $decision->reason,
            'final_position' => $decision->finalPosition,
            'board_target' => $decision->boardTargetPosition,
        ]);

        return $data;
    }
}

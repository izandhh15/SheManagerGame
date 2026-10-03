<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Services\MatchdayService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Match\Services\MatchNarrativeService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Services\SeasonTransitionChunkService;
use App\Modules\Season\Services\DualTurnService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShowGame
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly MatchdayService $matchdayService,
        private readonly MatchNarrativeService $narrativeService,
        private readonly NotificationService $notificationService,
        private readonly CompetitionViewService $competitionViewService,
        private readonly MatchFinalizationService $finalizationService,
        private readonly DualTurnService $dualTurn,
        private readonly \App\Modules\Stadium\Services\NationalVenueOrganizationService $venueOrg,
        private readonly SeasonTransitionChunkService $transitionChunks,
    ) {}

    public function __invoke(string $gameId)
    {
        try {
            return $this->show($gameId);
        } catch (ModelNotFoundException $e) {
            // Unknown game id: stays a 404, not a dashboard redirect.
            throw $e;
        } catch (\Throwable $e) {
            // Corrupt save (orphaned team, unreadable JSON or dates…) must
            // never 500 the entry route: bounce to the dashboard with the
            // same quarantine notice the dashboard itself shows.
            report($e);

            return redirect()->route('dashboard')->with('warning', __('game.save_load_failed'));
        }
    }

    private function show(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if (! $game->team) {
            // The team row this save points at is gone (deleted reference
            // data, half-finished deletion…): treat it as corrupt rather
            // than blowing up on a null relation below.
            throw new \RuntimeException("Game {$gameId} has no team.");
        }

        // Redirect to welcome tutorial if not yet completed (new games only)
        if ($game->needsWelcome()) {
            return redirect()->route('game.welcome', $gameId);
        }

        // Redirect to new-season setup if setup or new-season setup not completed
        if (!$game->isSetupComplete() || $game->needsNewSeasonSetup()) {
            return redirect()->route('game.new-season', $gameId);
        }

        // National teams: prompt the squad picker for each FIFA window so
        // the convocatoria happens per window, not at game creation. Fires
        // while a break is underway or within PROMPT_DAYS_BEFORE days
        // before it — never months early (the 60-day relevantWindow
        // lookahead would nag on almost every visit during the autumn
        // break cluster). A confirmed convocatoria is never re-prompted.
        if ($game->team->type === 'national' && $game->current_date) {
            $window = \App\Modules\Season\Services\NationalSquadService::promptWindow($game);
            $confirmedWindow = $game->national_squad_window?->format('Y-m-d');
            if ($window && $confirmedWindow !== $window['start']) {
                return redirect()->route('game.national-squad-picker', $gameId);
            }
        }

        // Show loading screen while season transition runs in background
        if ($game->isTransitioningSeason()) {
            // Recovery: run a time-boxed chunk if stuck for > 2 minutes.
            // (Re-dispatching the full sync job just timed out again on
            // serverless; chunks of ~25s always fit in HTTP timeouts.)
            if ($game->season_transitioning_at->lt(now()->subMinutes(2))) {
                $chunkResult = $this->transitionChunks->runChunk($game, 25.0);
                // Only refresh the flag if the transition is STILL running.
                // runChunk sets season_transitioning_at to null when it
                // completes; unconditionally touching it here would resurrect
                // the flag and re-run all 48 processors on the new season.
                if (!($chunkResult['done'] ?? false)) {
                    $game->update(['season_transitioning_at' => now()]);
                }
            }
            $isTournament = $game->isTournamentMode();
            return view('game-loading', [
                'game' => $game,
                'title' => $isTournament ? __('game.preparing_tournament') : __('game.preparing_season'),
                'message' => $isTournament ? __('game.setup_tournament_loading_message') : __('game.setup_loading_message'),
                'showCrest' => true,
            ]);
        }

        // Mandatory pre-season setup: the player must choose their friendlies
        // before reaching the dashboard. Fires once the season transition has
        // finished (so the game is fully built) — after "Begin Season" for
        // transitions, and right after the welcome tutorial for new careers.
        if ($game->needsPreseasonOpponentSelection()) {
            return redirect()->route('game.preseason-setup', $gameId);
        }

        // Consume a completed matchday advance before any background-job
        // loading screens. When the user just advanced into a live match,
        // remaining AI batches and career actions often process in the
        // background — the live-match view has its own polling for those
        // (processingStatusUrl), so the user can watch the match while the
        // background work continues. Gating entry into the live match on
        // those flags would show an unwanted "just the user's crest"
        // loading screen after the advance overlay.
        if ($advanceResult = $game->matchday_advance_result) {
            $game->update(['matchday_advance_result' => null]);
            $result = MatchdayAdvanceResult::fromArray($advanceResult);

            return match ($result->type) {
                'live_match' => redirect()->route('game.live-match', [
                    'gameId' => $gameId,
                    'matchId' => $result->matchId,
                ]),
                // Tournament mode goes straight to tournament-end (no
                // "between matches" dashboard exists). Season-based modes
                // fall through to render the dashboard with $nextMatch=null
                // so the user can browse their club one last time before
                // committing to the season transition.
                'season_complete' => $game->isTournamentMode()
                    ? redirect()->route('game.tournament-end', $gameId)
                    : redirect()->route('show-game', $gameId),
                'done' => redirect()->route('show-game', $gameId),
                'blocked' => $result->pendingAction && $result->pendingAction['route']
                    ? redirect()->route($result->pendingAction['route'], $gameId)->with('warning', __('messages.action_required'))
                    : redirect()->route('show-game', $gameId)->with('warning', __('messages.action_required')),
            };
        }

        // Safety net: if the user abandoned a live match without clicking
        // Continue (back button, browser close, etc.) and never triggered
        // another advance, the match stays played=true with standings
        // unapplied. MatchdayOrchestrator's own finalizePendingMatch only
        // fires on the next advance(), which never happens at end-of-season.
        // Refresh $game afterward because finalize() may advance current_date
        // and generate new matches. Best-effort: a DB hiccup must not 500 the
        // dashboard.
        if ($game->pending_finalization_match_id) {
            try {
                $this->finalizationService->finalizePendingIfAny($gameId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ShowGame: finalizePendingIfAny failed (non-fatal)', [
                    'game_id' => $gameId,
                    'error' => $e->getMessage(),
                ]);
            }
            $game = $game->refresh();
        }

        // Show loading screen while career actions are processing in background
        $game->clearStuckCareerActions();
        if ($game->isProcessingCareerActions()) {
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.processing_career_actions'),
                'message' => __('game.processing_career_actions_message'),
                'showCrest' => true,
            ]);
        }

        // Show loading screen while matchday advance runs in background
        if ($game->isAdvancingMatchday()) {
            $nextMatch = $this->loadNextMatch($game);

            if ($nextMatch) {
                return view('game-loading-matchday', [
                    'game' => $game,
                    'nextMatch' => $nextMatch,
                ]);
            }

            // User's team has finished the season but other competitions
            // still have AI-only fixtures to simulate. Use a generic screen —
            // no club crest, no "teams warming up" copy — because the user
            // isn't playing.
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.simulating_other_matches'),
                'message' => __('game.simulating_other_matches_message'),
                'showCrest' => false,
            ]);
        }

        // Fast mode takes over the dashboard — redirect only after all
        // transient-state checks (transition/processing/advance) have been
        // handled above, to avoid redirect loops with ShowFastMode.
        // Live-match finalization still happens in the normal flow, so this
        // redirect is skipped when a match is pending finalization.
        if ($game->isFastMode() && ! $game->pending_finalization_match_id) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        $nextMatch = $this->loadNextMatch($game);
        $hasRemainingMatches = !$nextMatch && $game->matches()->where('played', false)->exists();

        // Tournament mode: auto-redirect to simulate remaining matches
        // when the player is eliminated (no next match but matches remain)
        if ($game->isTournamentMode() && !$nextMatch && $hasRemainingMatches) {
            return redirect()->route('game.simulate-tournament', $gameId);
        }

        // Tournament complete: redirect to tournament-end. Season-based
        // modes fall through and render the dashboard with $nextMatch=null
        // so the user can browse their club before clicking through to the
        // season summary (the irreversible "Start New Season" lives there).
        if (!$nextMatch && !$hasRemainingMatches && $game->isTournamentMode()) {
            return redirect()->route('game.tournament-end', $gameId);
        }

        // The inbox holds the current matchday's notifications. Everything is
        // marked read at the start of each advance (MatchdayOrchestrator::advance),
        // so an unread-only query auto-clears the feed every matchday. Rows no
        // longer mark themselves read on click, so notifications persist through
        // the matchday — clicking one navigates without dismissing it.
        $notifications = $this->notificationService->getNotifications($game->id, true, 20);
        $groupedNotifications = $notifications->groupBy(fn ($n) => $n->game_date?->format('Y-m-d') ?? 'unknown');

        $dashboardContext = $this->competitionViewService->resolveDashboardContext($game, $nextMatch);

        $viewData = [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'hasRemainingMatches' => $hasRemainingMatches,
            // Dual mode: when the partner half has an earlier pending match,
            // this half is frozen — the panel shows a "stopped dead" banner
            // pointing at the partner instead of a playable next match.
            'dualForcedPartner' => $this->dualTurn->mustPlayPartnerFirst($game),
            'homeStanding' => $nextMatch ? GameStanding::forTeamInCompetition($game, $nextMatch->home_team_id, $nextMatch->competition_id) : null,
            'awayStanding' => $nextMatch ? GameStanding::forTeamInCompetition($game, $nextMatch->away_team_id, $nextMatch->competition_id) : null,
            'playerForm' => $this->calendarService->getTeamForm($game->id, $game->team_id),
            'opponentForm' => $this->getOpponentForm($game, $nextMatch),
            'upcomingFixtures' => $this->calendarService->getUpcomingFixtures($game),
            'groupedNotifications' => $groupedNotifications,
            'unreadNotificationCount' => $this->notificationService->getUnreadCount($game->id),
            'dashboardContext' => $dashboardContext,
            // National teams: home matches in Nations League / qualifiers
            // still waiting for the manager to organize a venue.
            'pendingVenues' => $game->isTournamentMode() ? $this->venueOrg->pendingMatches($game)->count() : 0,
        ];

        // Generate pre-match narrative snippets. Tournament mode renders 1-2 in
        // the next-match card; season-based modes surface a few more as the
        // wide-column "match preview" lead on the dashboard. Category diversity
        // in selectTop() keeps the mix varied (at most one per category).
        if ($nextMatch) {
            $isHome = $nextMatch->home_team_id === $game->team_id;

            $viewData['narratives'] = $this->narrativeService->generateWithPressure(
                $game,
                $nextMatch,
                $isHome ? $viewData['homeStanding'] : $viewData['awayStanding'],
                $isHome ? $viewData['awayStanding'] : $viewData['homeStanding'],
                $viewData['playerForm'],
                $viewData['opponentForm'],
                limit: $game->isTournamentMode() ? 2 : 4,
            );
        }

        // Wide-column ordering: the notifications inbox leads (actionable
        // per-matchday events), with News below as the atmospheric layer. The
        // empty inbox is hidden only when News can lead in its place; in
        // tournament mode News isn't rendered here (it stays in the next-match
        // card), so the inbox stays even when empty to keep the column populated.
        $showNews = !empty($viewData['narratives'] ?? null) && !$game->isTournamentMode();
        $viewData['showNews'] = $showNews;
        $viewData['showInbox'] = $groupedNotifications->isNotEmpty() || !$showNews;

        // Add knockout progress for tournament mode
        if ($game->isTournamentMode()) {
            $viewData['tournamentTie'] = $this->getPlayerTournamentTie($game);

            if ($nextMatch?->cup_tie_id) {
                $viewData['nextRoundPreview'] = $this->getNextRoundPreview($nextMatch->cupTie);
            }
        }

        // Add pre-season flag (hides the standings/cup-path card on the dashboard).
        if ($game->isInPreSeason()) {
            $viewData['isPreSeason'] = true;
        }

        return view('game', $viewData);
    }

    private function loadNextMatch(Game $game): ?GameMatch
    {
        $nextMatch = $this->matchdayService->getNextPlayerMatch($game);

        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam', 'competition']);
        }

        return $nextMatch;
    }

    private function getOpponentForm(Game $game, ?GameMatch $nextMatch): array
    {
        if (!$nextMatch) {
            return [];
        }

        $opponentId = $nextMatch->home_team_id === $game->team_id
            ? $nextMatch->away_team_id
            : $nextMatch->home_team_id;

        return $this->calendarService->getTeamForm($game->id, $opponentId);
    }

    private function getPlayerTournamentTie(Game $game): ?CupTie
    {
        return CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch'])
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->orderByDesc('round_number')
            ->first();
    }

    /**
     * Find the opposite tie in the bracket that determines the next-round opponent.
     *
     * Ties within a round are paired by bracket_position order: indices 0↔1, 2↔3, etc.
     * Returns an array with the opposite tie and, if resolved, the actual opponent team.
     *
     * @return array{tie: CupTie, opponent: ?Team}|null
     */
    private function getNextRoundPreview(CupTie $currentTie): ?array
    {
        $tiesInRound = CupTie::with(['homeTeam', 'awayTeam', 'winner'])
            ->where('game_id', $currentTie->game_id)
            ->where('competition_id', $currentTie->competition_id)
            ->where('round_number', $currentTie->round_number)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        if ($tiesInRound->count() < 2) {
            return null; // Final — no next round
        }

        $index = $tiesInRound->search(fn ($t) => $t->id === $currentTie->id);

        if ($index === false) {
            return null;
        }

        $oppositeIndex = ($index % 2 === 0) ? $index + 1 : $index - 1;
        $oppositeTie = $tiesInRound->get($oppositeIndex);

        if (! $oppositeTie) {
            return null;
        }

        return [
            'tie' => $oppositeTie,
            'opponent' => $oppositeTie->completed ? $oppositeTie->winner : null,
        ];
    }

}

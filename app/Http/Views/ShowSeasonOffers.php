<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Manager\Services\AcademyCareerService;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Report\Services\SeasonSummaryService;

/**
 * Renders the pro-manager between-seasons decision screen: pending job
 * offers from other clubs plus the option to stay at the current club
 * (suppressed when the manager has been fired). The user's choice on this
 * page is what kicks off the season-closing pipeline — see
 * AcceptSeasonOffer and DeclineSeasonOffers, which both re-enter
 * StartNewSeason after persisting the decision.
 *
 * Visiting this page generates the offers if they don't yet exist. The
 * service-side idempotency guard makes refresh/back-nav safe.
 */
class ShowSeasonOffers
{
    public function __construct(
        private readonly SeasonSummaryService $seasonSummaryService,
        private readonly JobOfferService $jobOfferService,
        private readonly MatchFinalizationService $finalizationService,
        private readonly PlayoffGeneratorFactory $playoffFactory,
        private readonly AcademyCareerService $academyCareerService,
    ) {}

    public function __invoke(string $gameId)
    {
        // Same defensive finalize as ShowSeasonEnd: a match abandoned on
        // the live screen would otherwise leave standings stale, which
        // would feed a wrong grade into ensureEndOfSeasonOffersGenerated.
        $this->finalizationService->finalizePendingIfAny($gameId);

        $game = Game::with('team')->findOrFail($gameId);

        abort_unless($game->isProManagerMode(), 404);
        abort_if($game->isTournamentMode(), 404);

        if ($game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        $unplayedMatches = $game->matches()
            ->where('played', false)
            ->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.season_not_complete'));
        }

        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                return redirect()->route('show-game', $gameId)
                    ->with('error', __('messages.season_not_complete'));
            }
        }

        $this->jobOfferService->ensureEndOfSeasonOffersGenerated($game);

        // Academy career: roll for a promotion to the parent team. If the
        // dice land, create a special promotion offer (the user can accept
        // it to move up, or decline to stay at the filial).
        $promotionOffer = null;
        if ($game->isAcademyCareer() && $game->team->isReserveTeam()) {
            $promotionOffer = $this->maybeCreatePromotionOffer($game);
        }

        [$jobOffers, $pendingTeamSwitchOffer] = $this->seasonSummaryService->buildProManagerOffers($game);

        return view('season-offers', [
            'game' => $game,
            'jobOffers' => $jobOffers,
            'pendingTeamSwitchOffer' => $pendingTeamSwitchOffer,
            'promotionOffer' => $promotionOffer,
            'firedAtSeasonEnd' => $game->wasFiredThisSeason(),
            'positionsByOfferId' => $this->seasonSummaryService->lastSeasonPositionsByOfferId($game, $jobOffers),
            'goalLabelsByOfferId' => $this->seasonSummaryService->seasonGoalLabelsByOfferId($jobOffers),
            'nextSeasonLabel' => Game::formatSeason((string) ((int) $game->season + 1)),
        ]);
    }

    /**
     * Roll for an academy promotion. Returns the created offer, or null if
     * the roll failed or an offer already exists for this season.
     */
    private function maybeCreatePromotionOffer(Game $game): ?ManagerJobOffer
    {
        // Already have a promotion offer for this season?
        $existing = ManagerJobOffer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('offer_type', ManagerJobOffer::TYPE_ACADEMY_PROMOTION)
            ->first();

        if ($existing) {
            return $existing->status === ManagerJobOffer::STATUS_PENDING ? $existing : null;
        }

        // Get the season grade from the evaluation (simplified: use the
        // job offer service's grade resolution via reflection, or just
        // roll with a base probability).
        // For now, use a simple heuristic based on final league position.
        $grade = $this->resolveSimpleGrade($game);

        $parentTeam = $this->academyCareerService->rollForPromotion($game, $grade);

        if (!$parentTeam) {
            return null;
        }

        return ManagerJobOffer::create([
            'user_id' => $game->user_id,
            'game_id' => $game->id,
            'team_id' => $parentTeam->id,
            'season' => $game->season,
            'offer_type' => ManagerJobOffer::TYPE_ACADEMY_PROMOTION,
            'status' => ManagerJobOffer::STATUS_PENDING,
            'target_reputation_level' => 'promotion',
            'created_on_game_date' => $game->current_date,
        ]);
    }

    /**
     * Simple grade based on final league position for the promotion roll.
     */
    private function resolveSimpleGrade(Game $game): string
    {
        $standing = \App\Models\GameStanding::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('season', $game->season)
            ->orderBy('position')
            ->first();

        if (!$standing || !$standing->position) {
            return 'met';
        }

        $position = $standing->position;

        if ($position === 1) {
            return 'champion';
        }
        if ($position <= 3) {
            return 'top3';
        }
        if ($position <= 8) {
            return 'met';
        }

        return 'below';
    }
}

<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonOpponentService;
use App\Modules\Season\Services\PreseasonInvitationService;
use App\Modules\Season\Services\PreseasonTourService;
use App\Modules\Season\Services\TrainingStageService;
use App\Support\CountryNames;

class ShowPreseasonSetup
{
    public function __construct(
        private readonly PreseasonOpponentService $opponentService,
        private readonly PreseasonInvitationService $invitationService,
        private readonly PreseasonTourService $tourService,
        private readonly TrainingStageService $stageService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Still building the season — let show-game render the loading screen.
        if (! $game->isSetupComplete() || $game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Selection already done (or not applicable) — back to the dashboard.
        if (! $game->needsPreseasonOpponentSelection()) {
            return redirect()->route('show-game', $gameId);
        }

        // The machine invites you: generate AI invitations (idempotent).
        $this->invitationService->generateFor($game);

        $teams = $this->opponentService->candidateTeamsGroupedByCountry($game);
        $slots = $this->opponentService->fixtureSlots($game);
        $invitations = $this->invitationService->pendingFor($game);
        $acceptedInvitations = $this->invitationService->acceptedFor($game);

        // The club's automatic 5th friendly (first team vs. filial): shown as
        // a locked preview — it gets scheduled when pre-season is confirmed.
        $familyDerbyOpponent = $this->opponentService->familyDerbyOpponent($game);

        // Accepted invitations as locked slots for the Alpine picker.
        $acceptedForJs = $acceptedInvitations->map(fn ($inv) => [
            'slot' => $inv->slot,
            'teamId' => $inv->inviting_team_id,
            'teamName' => $inv->invitingTeam->name,
            'teamImage' => $inv->invitingTeam->image,
            'trophyName' => $inv->trophy_name,
            'stadiumName' => $inv->stadium_name,
            'invitationId' => $inv->id,
        ])->values()->all();

        // Club training stage (concentración): one per season, charged to the
        // transfer budget. Only the current season's config counts.
        $stageConfig = $game->training_stage;
        if (! is_array($stageConfig) || ($stageConfig['season'] ?? null) !== $game->season) {
            $stageConfig = null;
        }

        return view('preseason-setup', [
            'game' => $game,
            'teams' => $teams,
            'slots' => $slots,
            'invitations' => $invitations,
            'acceptedInvitations' => $acceptedInvitations,
            'acceptedForJs' => $acceptedForJs,
            'familyDerbyOpponent' => $familyDerbyOpponent,
            'tourDestinations' => $this->tourService->destinationOptions(),
            'tourEligible' => $this->tourService->isEligible($game),
            'preseasonTour' => $game->preseason_tour,
            'preseasonTourName' => $game->preseason_tour
                ? $this->tourService->destinationName($game->preseason_tour['destination'] ?? '')
                : null,
            'tourBudgetEuros' => (int) (($game->currentInvestment?->transfer_budget ?? 0) / 100),
            'stageService' => $this->stageService,
            'clubStageConfig' => $stageConfig,
            'stageDurations' => TrainingStageService::DURATIONS,
            'stageIntensities' => TrainingStageService::INTENSITIES,
            'stageFocuses' => TrainingStageService::FOCUSES,
            'stageCountries' => CountryNames::STAGE_DESTINATIONS,
            'stageHomeCountry' => CountryNames::name($game->team?->country),
            'stageBudgetEuros' => (int) (($game->currentInvestment?->transfer_budget ?? 0) / 100),
        ]);
    }
}

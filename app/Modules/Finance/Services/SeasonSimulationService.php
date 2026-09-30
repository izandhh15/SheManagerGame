<?php

namespace App\Modules\Finance\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\SimulatedSeason;
use App\Models\TeamReputation;

class SeasonSimulationService
{
    /**
     * Simulate a league season for a non-played competition.
     *
     * Uses reputation-based fuzzy sorting: each team's reputation points
     * are jittered by a random offset, then sorted to produce plausible
     * final standings without simulating individual matches.
     */
    public function simulateLeague(Game $game, Competition $competition): SimulatedSeason
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->pluck('team_id')
            ->toArray();

        // Get reputation points for all teams (single query)
        $reputations = TeamReputation::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->pluck('reputation_points', 'team_id');

        // Fill missing teams from ClubProfile, defaulting to LOCAL tier
        $missing = array_diff($teamIds, $reputations->keys()->all());
        if (!empty($missing)) {
            $fallback = ClubProfile::whereIn('team_id', $missing)
                ->pluck('reputation_level', 'team_id')
                ->map(fn ($level) => TeamReputation::pointsForTier($level));
            $reputations = $reputations->merge($fallback);

            // Teams with neither TeamReputation nor ClubProfile get LOCAL tier
            $stillMissing = array_diff($missing, $fallback->keys()->all());
            $defaultPoints = TeamReputation::pointsForTier(ClubProfile::REPUTATION_LOCAL);
            foreach ($stillMissing as $teamId) {
                $reputations[$teamId] = $defaultPoints;
            }
        }

        // Fuzzy sort: jitter reputation by ±40 points (tiers are 100 apart),
        // allowing upsets across adjacent tiers
        $fuzzed = $reputations->map(fn ($points) => $points + random_int(-100, 100));
        $results = $fuzzed->sortDesc()->keys()->values()->toArray();

        return SimulatedSeason::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
                'competition_id' => $competition->id,
            ],
            ['results' => $results],
        );
    }
}

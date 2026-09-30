<?php

namespace App\Modules\Season\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Season\Jobs\SetupTournamentGame;
use App\Models\Game;
use App\Models\GameTactics;
use App\Models\Team;
use Ramsey\Uuid\Uuid;

class TournamentCreationService
{
    /**
     * @param array<string>|null $squadPlayerIds Chosen player_ids (national-team games only)
     */
    public function create(string $userId, string $teamId, string $competitionId = 'WC2026', ?array $squadPlayerIds = null): Game
    {
        $gameId = Uuid::uuid4()->toString();

        $team = Team::findOrFail($teamId);

        $isNational = $competitionId === 'WWCQ';

        $game = Game::create([
            'id' => $gameId,
            'user_id' => $userId,
            'game_mode' => Game::MODE_TOURNAMENT,
            'country' => $team->fifa_code ?? 'XXX',
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'season' => $isNational ? '2026' : '2025',
            'base_season' => $isNational ? '2026' : '2025',
            'current_date' => $isNational ? '2026-08-15' : '2026-06-11',
            'needs_welcome' => true,
            'needs_new_season_setup' => true,
            'setup_completed_at' => null,
            'national_squad_player_ids' => $squadPlayerIds,
        ]);

        // Create default tactical settings
        GameTactics::create(['game_id' => $gameId, 'default_formation' => Formation::F_4_3_3->value]);

        SetupTournamentGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
        );

        return $game;
    }
}

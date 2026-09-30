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
     * Women's World Cup qualifying competitions: one per FIFA confederation.
     * v1 format — each confederation's qualifying runs as a drawn group of 6
     * in its own competition row, and games store the confederation's id.
     *
     * 'WWCQ' is the legacy beta id (a single global competition). It stays in
     * WQC_IDS so old saves keep working; new games always resolve through
     * competitionIdForConfederation().
     *
     * competitionIdForConfederation() is the single mapping point for the
     * confederation → competition resolution — the dual-mode worker builds
     * on it (see InitNationalGame).
     */
    public const CONFEDERATION_COMPETITIONS = [
        'UEFA'     => 'WQUEFA',
        'AFC'      => 'WQAFC',
        'CAF'      => 'WQCAF',
        'CONCACAF' => 'WQCONC',
        'CONMEBOL' => 'WQCONM',
        'OFC'      => 'WQOFC',
    ];

    /** Legacy beta id: the original single global qualifier competition. */
    public const LEGACY_QUALIFIER_ID = 'WWCQ';

    /** Every competition id accepted as "national-team World Cup qualifying". */
    public const WQC_IDS = [
        'WQUEFA',
        'WQAFC',
        'WQCAF',
        'WQCONC',
        'WQCONM',
        'WQOFC',
        'WWCQ',
    ];

    /** UEFA Women's Nations League competition id. */
    public const WNL_ID = 'WNL';

    /** Every competition id accepted as a national-team competition. */
    public const NATIONAL_TEAM_COMPETITION_IDS = [
        'WQUEFA',
        'WQAFC',
        'WQCAF',
        'WQCONC',
        'WQCONM',
        'WQOFC',
        'WWCQ',
        'WNL',
    ];

    /**
     * Resolve the qualifying competition for a team's FIFA confederation.
     * Unknown or missing confederations fall back to the legacy WWCQ id,
     * whose setup job runs the legacy global draw.
     */
    public static function competitionIdForConfederation(?string $confederation): string
    {
        return self::CONFEDERATION_COMPETITIONS[$confederation] ?? self::LEGACY_QUALIFIER_ID;
    }

    /**
     * @param array<string>|null $squadPlayerIds Chosen player_ids (national-team games only)
     */
    public function create(string $userId, string $teamId, string $competitionId = 'WC2026', ?array $squadPlayerIds = null): Game
    {
        $gameId = Uuid::uuid4()->toString();

        $team = Team::findOrFail($teamId);

        $isNational = in_array($competitionId, self::NATIONAL_TEAM_COMPETITION_IDS, true);

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

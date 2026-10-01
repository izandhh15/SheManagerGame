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
        'UEFA'     => 'WNL',
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

    /** FIFA Women's World Cup 2027 (final tournament) competition id. */
    public const WWCU27_ID = 'WWCU27';

    /** UEFA Women's Euro 2029 (final tournament) competition id. */
    public const WEURO_ID = 'WEURO';

    /** UEFA Women's Euro 2029 qualifying competition id. */
    public const WEUROQ_ID = 'WEUROQ';

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
        'WWCU27',
        'WEURO',
        'WEUROQ',
    ];

    /**
     * Competition ids of the final tournaments (World Cup / Euros).
     * These are set up with groups + knockout instead of a drawn
     * qualifier group.
     */
    public const NATIONAL_TEAM_FINAL_IDS = [
        'WWCU27',
        'WEURO',
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
     * National team competition sequence (Izan's unified calendar):
     * Nations League → World Cup Qualifying → (World Cup 2027) →
     * Nations League → Euro Qualifying → (Euro 2029) → Nations League…
     *
     * After each Nations League, the cycle alternates: the first cycle
     * runs the World Cup qualifiers; once the game has played a World
     * Cup, the next cycle runs the Euro qualifiers, and so on.
     * Returns the next competition id after the given one, or null if
     * the sequence ends.
     */
    public static function nextCompetitionInSequence(string $currentCompetitionId, ?Game $game = null): ?string
    {
        return match ($currentCompetitionId) {
            self::WNL_ID => self::afterNationsLeague($game),
            'WQUEFA' => self::WWCU27_ID,      // World Cup Qualifying → World Cup 2027
            // Other confederations' qualifiers also lead to the World Cup.
            'WQAFC', 'WQCAF', 'WQCONC', 'WQCONM', 'WQOFC' => self::WWCU27_ID,
            self::WWCU27_ID => self::afterWorldCup($game),
            self::WEUROQ_ID => self::WEURO_ID, // Euro Qualifying → Euro 2029
            self::WEURO_ID => self::WNL_ID,   // Euro → Nations League
            default => null,
        };
    }

    /**
     * After the World Cup, each team returns to its confederation's
     * competition (Nations League for UEFA, qualifiers elsewhere).
     */
    private static function afterWorldCup(?Game $game): string
    {
        return self::competitionIdForConfederation($game?->team?->confederation);
    }

    /**
     * After a Nations League season, alternate between the World Cup
     * cycle and the Euro cycle based on which final the game played
     * most recently (read from its season archives).
     */
    private static function afterNationsLeague(?Game $game): string
    {
        if ($game !== null && self::lastPlayedFinal($game) === self::WWCU27_ID) {
            return self::WEUROQ_ID; // last final was the World Cup → Euro cycle
        }

        return 'WQUEFA'; // no final yet, or last final was the Euro → World Cup cycle
    }

    /**
     * The most recent final tournament (WWCU27 / WEURO) this game played,
     * from its season archives — or null if it hasn't played one yet.
     */
    private static function lastPlayedFinal(Game $game): ?string
    {
        $archives = \App\Models\SeasonArchive::where('game_id', $game->id)
            ->orderBy('id')
            ->get(['final_standings']);

        $lastFinal = null;
        foreach ($archives as $archive) {
            foreach ($archive->final_standings ?? [] as $row) {
                $cid = $row['competition_id'] ?? null;
                if (in_array($cid, self::NATIONAL_TEAM_FINAL_IDS, true)) {
                    $lastFinal = $cid;
                }
            }
        }

        return $lastFinal;
    }

    /**
     * Did the game's team finish in the top 2 of the given qualifier
     * competition in its most recent archived season? Used to gate
     * entry to the final tournaments (WWCU27 / WEURO).
     *
     * Returns true when there is no archived standing to check
     * (graceful fallback — never trap the user over missing data).
     */
    public static function userQualifiedForFinal(Game $game, string $qualifierCompetitionId): bool
    {
        $archive = \App\Models\SeasonArchive::where('game_id', $game->id)
            ->orderByDesc('id')
            ->first(['final_standings']);

        if (!$archive) {
            return true;
        }

        foreach ($archive->final_standings ?? [] as $row) {
            if (($row['competition_id'] ?? null) === $qualifierCompetitionId
                && ($row['team_id'] ?? null) === $game->team_id) {
                return ($row['position'] ?? 99) <= 2;
            }
        }

        return true;
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
            'current_date' => $isNational ? '2026-07-01' : '2026-06-11',
            'needs_welcome' => true,
            'needs_new_season_setup' => true,
            'setup_completed_at' => null,
            'national_squad_player_ids' => $squadPlayerIds,
            // Venue organization budget for Nations League / qualifiers.
            'federation_budget' => $isNational ? $this->budgetForNation($team) : 2000000,
        ]);

        // Create default tactical settings
        GameTactics::create(['game_id' => $gameId, 'default_formation' => Formation::F_4_3_3->value]);

        SetupTournamentGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
        );

        return $game;
    }

    /**
     * Federation venue-organization budget (euros) for a national team.
     */
    public function budgetForNation(Team $team): int
    {
        $top = [
            'Spain', 'United States', 'England', 'Germany', 'France', 'Brazil',
            'Japan', 'Netherlands', 'Sweden', 'Canada', 'Australia', 'Norway',
            'Denmark', 'Italy', 'Iceland', 'South Korea',
        ];
        if (in_array($team->name, $top, true)) {
            return 15_000_000;
        }
        if ($team->confederation === 'UEFA') {
            return 8_000_000;
        }

        return 3_000_000;
    }
}

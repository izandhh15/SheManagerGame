<?php

namespace App\Modules\Season\Jobs;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationBiasResolver;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Services\SeasonInitializationService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\GameTactics;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SetupTournamentGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const COMPETITION_ID = 'WC2026';

    public function __construct(
        public string $gameId,
        public string $teamId,
    ) {
        $this->onQueue('setup');
    }

    public function tags(): array
    {
        return ['game:' . $this->gameId];
    }

    public function handle(
        NotificationService $notificationService,
        FormationRecommender $formationRecommender,
        FormationBiasResolver $formationBiasResolver,
    ): void {
        $game = Game::find($this->gameId);
        if (!$game || $game->isSetupComplete()) {
            return;
        }

        // Women's World Cup Qualifiers (beta): the user's group is drawn at
        // setup time instead of coming from a fixed groups.json. Each FIFA
        // confederation runs its own qualifier (WQUEFA, WQAFC, ...); WWCQ is
        // the legacy alias for the original single global competition.
        // WNL (UEFA Women's Nations League) uses the real 2025 groups.
        if (in_array($game->competition_id, TournamentCreationService::NATIONAL_TEAM_COMPETITION_IDS, true)) {
            $this->handleNationalQualifiers($game, $notificationService, $formationRecommender, $formationBiasResolver);
            return;
        }

        // Load groups.json for fixture data and group assignments (cached for 1 hour)
        $groupsData = Cache::remember('wc2026_groups', 3600, function () {
            $groupsPath = base_path('data/2025/WC2026/groups.json');
            return json_decode(file_get_contents($groupsPath), true);
        });

        // Build FIFA code → Team UUID map from the database (cached for 1 hour)
        $nationalTeams = Cache::remember('wc2026_national_teams', 3600, function () {
            return Team::worldCupEligible()->get(['id', 'fifa_code']);
        });
        $teamKeyMap = $nationalTeams->pluck('id', 'fifa_code')->toArray();

        DB::transaction(function () use ($game, $groupsData, $teamKeyMap, $nationalTeams, $notificationService, $formationRecommender, $formationBiasResolver) {
            // Step 1: Create competition entries for all WC teams
            $this->createCompetitionEntries();

            // Step 2: Create fixtures from groups.json
            $this->createFixtures($groupsData, $teamKeyMap);

            // Step 3: Create standings with group labels
            $this->createGroupStandings($groupsData, $teamKeyMap);

            // Step 4: Create game players from pre-computed templates
            $this->createGamePlayersFromTemplates();

            // Step 5: Pick a default formation that fits the user's squad.
            // National teams carry preferred_formation via their ClubProfile
            // (NATIONAL_TEAM_PREFERRED_FORMATION map), so the bias resolver
            // surfaces the curated identity (Spain → high press, Iran → deep
            // block, etc.) rather than the generic 4-3-3 placeholder.
            $this->setUserTeamDefaultFormation($formationRecommender, $formationBiasResolver);

            // Send welcome notification
            $teamName = $nationalTeams->firstWhere('id', $this->teamId)?->getRawOriginal('name') ?? '';
            $notificationService->notifyTournamentWelcome($game, self::COMPETITION_ID, $teamName);

            // Mark setup as complete
            Game::where('id', $this->gameId)->update(['setup_completed_at' => now()]);

            // Record activation event
            app(\App\Modules\Season\Services\ActivationTracker::class)
                ->record($game->user_id, \App\Models\ActivationEvent::EVENT_SETUP_COMPLETED, $this->gameId, \App\Models\Game::MODE_TOURNAMENT);
        });
    }

    /**
     * Setup for the Women's World Cup Qualifiers (beta).
     *
     * Draws a group of 6 within the user's confederation (legacy: 5 random
     * global opponents when the confederation pool can't fill the group),
     * creates competition entries, materialises players (the user's called-up
     * squad + full AI rosters from templates), generates a 10-matchday double
     * round-robin with the shared league fixture generator, and initialises
     * the standings.
     */
    private function handleNationalQualifiers(
        Game $game,
        NotificationService $notificationService,
        FormationRecommender $formationRecommender,
        FormationBiasResolver $formationBiasResolver,
    ): void {
        DB::transaction(function () use ($game, $notificationService, $formationRecommender, $formationBiasResolver) {
            // Step 1: get the group (real WNL groups, or drawn for qualifiers)
            $groupTeamIds = $game->competition_id === TournamentCreationService::WNL_ID
                ? $this->getWNLGroup($game)
                : $this->drawQualifierGroup($game);

            // Step 2: competition entries for the group teams
            $this->createQualifierEntries($game->competition_id, $groupTeamIds);

            // Step 3: game players (user's 23 + AI rosters)
            $this->createQualifierPlayers($game, $groupTeamIds);

            // Step 4: fixtures (10 matchdays, double round-robin)
            app(SeasonInitializationService::class)
                ->generateLeagueFixtures($this->gameId, $game->competition_id, $game->season);

            // Step 5: standings
            app(StandingsCalculator::class)
                ->initializeStandings($this->gameId, $game->competition_id, $groupTeamIds);

            // Step 6: default formation for the user's squad
            $this->setUserTeamDefaultFormation($formationRecommender, $formationBiasResolver);

            // Welcome notification
            $teamName = Team::find($this->teamId)?->getRawOriginal('name') ?? '';
            $notificationService->notifyTournamentWelcome($game, $game->competition_id, $teamName);

            // Mark setup as complete
            Game::where('id', $this->gameId)->update(['setup_completed_at' => now()]);

            // Record activation event
            app(\App\Modules\Season\Services\ActivationTracker::class)
                ->record($game->user_id, \App\Models\ActivationEvent::EVENT_SETUP_COMPLETED, $this->gameId, Game::MODE_TOURNAMENT);
        });
    }

    /**
     * Get the real UEFA Women's Nations League group for the user's team.
     *
     * Loads data/2026/WNL/groups.json and finds which of the 4 League A
     * groups contains the user's team (by FIFA code). Returns the 4 team
     * IDs for that group. Falls back to a drawn group if the team isn't
     * in League A (shouldn't happen for UEFA teams in v1).
     */
    private function getWNLGroup(Game $game): array
    {
        $groupsPath = base_path('data/2026/WNL/groups.json');
        if (!file_exists($groupsPath)) {
            // Fallback to drawn group if data file missing
            return $this->drawQualifierGroup($game);
        }

        $groupsData = json_decode(file_get_contents($groupsPath), true);
        $userFifaCode = Team::where('id', $this->teamId)->value('fifa_code');

        if (!$userFifaCode) {
            return $this->drawQualifierGroup($game);
        }

        // Find the group containing the user's team
        $userGroup = null;
        foreach ($groupsData['groups'] ?? [] as $groupId => $group) {
            if (in_array($userFifaCode, $group['teams'] ?? [], true)) {
                $userGroup = $group;
                break;
            }
        }

        if (!$userGroup) {
            // Team not in League A — fall back to drawn group
            return $this->drawQualifierGroup($game);
        }

        // Map FIFA codes to team IDs (no season filter — matches
        // drawQualifierGroup, which doesn't scope teams by season either).
        $fifaCodes = $userGroup['teams'];
        $teams = Team::where('type', 'national')
            ->whereIn('fifa_code', $fifaCodes)
            ->pluck('id', 'fifa_code')
            ->toArray();

        // Ensure we have all 4 teams, with user's team first
        $groupTeamIds = [];
        foreach ($fifaCodes as $code) {
            if (isset($teams[$code])) {
                $groupTeamIds[] = $teams[$code];
            }
        }

        // If we couldn't find all 4, fall back to drawn group
        if (count($groupTeamIds) !== 4) {
            return $this->drawQualifierGroup($game);
        }

        return $groupTeamIds;
    }

    /**
     * Draw the qualifier group: the user's national team + 5 rivals.
     *
     * v1 format — confederation groups: rivals are drawn from the user's own
     * FIFA confederation (same 6-team group format as before), so a UEFA side
     * faces UEFA sides. Falls back to the legacy global draw when the
     * confederation pool can't fill a group (too few sides with ≥18 templated
     * players, or the user's team has no confederation set).
     */
    private function drawQualifierGroup(Game $game): array
    {
        // Tolerant read: if teams.confederation doesn't exist yet (migration
        // not applied in this environment), fall back to the global draw.
        $confederation = Schema::hasColumn('teams', 'confederation')
            ? Team::where('id', $this->teamId)->value('confederation')
            : null;

        if ($confederation !== null) {
            $rivals = $this->drawRivals($game, $confederation);
            if (count($rivals) === 5) {
                return array_merge([$this->teamId], $rivals);
            }
        }

        return array_merge([$this->teamId], $this->drawRivals($game, null));
    }

    /**
     * Draw up to 5 random opponents, preferring sides with a playable roster
     * (≥18 templated players for the game's season).
     *
     * @param string|null $confederation Restrict the draw to this FIFA
     *        confederation, or null for the legacy global draw.
     */
    private function drawRivals(Game $game, ?string $confederation): array
    {
        $candidatesQuery = fn () => Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->where('id', '!=', $this->teamId)
            ->whereNotNull('fifa_code')
            ->when($confederation !== null, fn ($query) => $query->where('confederation', $confederation));

        $candidates = $candidatesQuery()->inRandomOrder()->limit(30)->pluck('id');

        $withRosters = DB::table('game_player_templates')
            ->where('season', $game->season)
            ->whereIn('team_id', $candidates)
            ->groupBy('team_id')
            ->havingRaw('COUNT(*) >= 18')
            ->pluck('team_id')
            ->shuffle()
            ->take(5)
            ->all();

        if (count($withRosters) < 5) {
            $extra = $candidatesQuery()
                ->whereNotIn('id', $withRosters)
                ->inRandomOrder()
                ->limit(5 - count($withRosters))
                ->pluck('id')
                ->all();
            $withRosters = array_merge($withRosters, $extra);
        }

        return $withRosters;
    }

    private function createQualifierEntries(string $competitionId, array $groupTeamIds): void
    {
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $rows = array_map(fn ($teamId) => [
            'game_id' => $this->gameId,
            'competition_id' => $competitionId,
            'team_id' => $teamId,
            'entry_round' => 1,
        ], $groupTeamIds);

        CompetitionEntry::insert($rows);
    }

    /**
     * Materialise players from templates: the user's called-up squad for
     * their team, full templated rosters for the 5 AI opponents.
     */
    private function createQualifierPlayers(Game $game, array $groupTeamIds): void
    {
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $columns = <<<'SQL'
            INSERT INTO game_players (
                id, game_id, player_id,
                transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), ?, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id, t.number, t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM game_player_templates t
        SQL;

        $aiTeamIds = array_values(array_diff($groupTeamIds, [$this->teamId]));

        if ($aiTeamIds !== []) {
            $placeholders = implode(',', array_fill(0, count($aiTeamIds), '?'));
            DB::insert(
                $columns . " WHERE t.season = ? AND t.team_id IN ($placeholders) ON CONFLICT (game_id, player_id) DO NOTHING",
                [$this->gameId, $game->season, ...$aiTeamIds]
            );
        }

        // User's team: only the called-up players. Fall back to the full
        // templated roster if no squad was stored (legacy games).
        $squadPlayerIds = $game->national_squad_player_ids ?? [];

        if ($squadPlayerIds !== []) {
            $placeholders = implode(',', array_fill(0, count($squadPlayerIds), '?'));
            DB::insert(
                $columns . " WHERE t.season = ? AND t.team_id = ? AND t.player_id IN ($placeholders) ON CONFLICT (game_id, player_id) DO NOTHING",
                [$this->gameId, $game->season, $this->teamId, ...$squadPlayerIds]
            );
        } else {
            DB::insert(
                $columns . ' WHERE t.season = ? AND t.team_id = ? ON CONFLICT (game_id, player_id) DO NOTHING',
                [$this->gameId, $game->season, $this->teamId]
            );
        }

        DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = ?
            WHERE gp.game_id = ?
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$game->season, $this->gameId]);
    }

    private function createCompetitionEntries(): void
    {
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $teamIds = CompetitionTeam::where('competition_id', self::COMPETITION_ID)
            ->where('season', '2025')
            ->pluck('team_id');

        $rows = $teamIds->map(fn ($teamId) => [
            'game_id' => $this->gameId,
            'competition_id' => self::COMPETITION_ID,
            'team_id' => $teamId,
            'entry_round' => 1,
        ])->toArray();

        foreach (array_chunk($rows, 500) as $chunk) {
            CompetitionEntry::insert($chunk);
        }
    }

    private function createFixtures(array $groupsData, array $teamKeyMap): void
    {
        if (GameMatch::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $matchRows = [];

        foreach ($groupsData as $groupLabel => $groupInfo) {
            foreach ($groupInfo['matches'] as $match) {
                $homeTeamId = $teamKeyMap[$match['home']] ?? null;
                $awayTeamId = $teamKeyMap[$match['away']] ?? null;

                if (!$homeTeamId || !$awayTeamId) {
                    continue;
                }

                $matchRows[] = [
                    'id' => Str::uuid()->toString(),
                    'game_id' => $this->gameId,
                    'competition_id' => self::COMPETITION_ID,
                    'round_number' => $match['round'],
                    'round_name' => __('game.group_stage') . ' - ' . __('game.matchday') . ' ' . $match['round'],
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'scheduled_date' => $match['date'],
                    'played' => false,
                ];
            }
        }

        foreach (array_chunk($matchRows, 500) as $chunk) {
            GameMatch::insert($chunk);
        }
    }

    private function createGroupStandings(array $groupsData, array $teamKeyMap): void
    {
        if (GameStanding::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $rows = [];
        foreach ($groupsData as $groupLabel => $groupInfo) {
            $position = 1;
            foreach ($groupInfo['teams'] as $teamKey) {
                $teamId = $teamKeyMap[$teamKey] ?? null;
                if (!$teamId) {
                    continue;
                }

                $rows[] = [
                    'game_id' => $this->gameId,
                    'competition_id' => self::COMPETITION_ID,
                    'group_label' => $groupLabel,
                    'team_id' => $teamId,
                    'position' => $position,
                    'prev_position' => null,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'points' => 0,
                ];
                $position++;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            GameStanding::insert($chunk);
        }
    }

    /**
     * Copy pre-computed templates into game_players (mirrors SetupNewGame pattern).
     */
    private function createGamePlayersFromTemplates(): void
    {
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        // Tournament mode (WC2026) only loads teams the user actually faces,
        // so every player here is "active" — they all need a match-state
        // satellite row from the start. Two single INSERT...SELECT statements
        // run entirely in Postgres; the match_state insert joins on player_id
        // *and* team_id so that, if the same player_id appears in multiple
        // templates for season 2025 (e.g., league + national), we copy
        // fitness/morale from the template that matches the inserted
        // game_player's team.
        $eligibleNationalTeamIds = Team::where('type', 'national')
            ->whereNotNull('fifa_code')
            ->pluck('id')
            ->all();

        if ($eligibleNationalTeamIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($eligibleNationalTeamIds), '?'));

        // National teams that have an explicit "called up" roster published in
        // their JSON (via game_player_template_tournament_info.is_called_up).
        // For those, AI opponents start the tournament with only that 26-man
        // squad. Teams without any called-up flag fall through to the legacy
        // behavior of copying every templated player, so the JSON files can be
        // updated incrementally without breaking unseeded nations.
        $teamsWithCalledUp = DB::table('game_player_template_tournament_info as ti')
            ->join('game_player_templates as t', 't.id', '=', 'ti.game_player_template_id')
            ->where('t.season', '2025')
            ->where('ti.is_called_up', true)
            ->whereIn('t.team_id', $eligibleNationalTeamIds)
            ->distinct()
            ->pluck('t.team_id')
            ->all();

        if ($teamsWithCalledUp !== []) {
            $calledUpPlaceholders = implode(',', array_fill(0, count($teamsWithCalledUp), '?'));
            $calledUpFilter = "AND (ti.is_called_up = true OR t.team_id NOT IN ($calledUpPlaceholders))";
            $calledUpBindings = $teamsWithCalledUp;
        } else {
            $calledUpFilter = '';
            $calledUpBindings = [];
        }

        DB::insert(<<<SQL
            INSERT INTO game_players (
                id, game_id, player_id,
                transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), ?, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id, NULL, t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM game_player_templates t
            LEFT JOIN game_player_template_tournament_info ti ON ti.game_player_template_id = t.id
            WHERE t.season = '2025'
              AND t.team_id IN ($placeholders)
              AND t.team_id <> ?
              $calledUpFilter
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL, [$this->gameId, ...$eligibleNationalTeamIds, $this->teamId, ...$calledUpBindings]);

        DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = '2025'
            WHERE gp.game_id = ?
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$this->gameId]);
    }

    /**
     * Pick the default formation that best fits the user's national team and
     * persist it on GameTactics. Mirrors the SetupNewGame equivalent — only
     * overwrites the placeholder set by TournamentCreationService so a setup
     * retry never clobbers a formation the user has since edited.
     */
    private function setUserTeamDefaultFormation(
        FormationRecommender $formationRecommender,
        FormationBiasResolver $formationBiasResolver,
    ): void {
        $tactics = GameTactics::where('game_id', $this->gameId)->first();
        if ($tactics === null) {
            return;
        }

        $placeholder = Formation::F_4_3_3->value;
        if ($tactics->default_formation !== $placeholder) {
            return;
        }

        $players = GamePlayer::with('matchState')
            ->where('game_id', $this->gameId)
            ->where('team_id', $this->teamId)
            ->get();

        if ($players->isEmpty()) {
            return;
        }

        $bias = $formationBiasResolver->resolveForTeam($this->gameId, $this->teamId);
        $formation = $formationRecommender->getBestFormation($players, $bias);

        $tactics->update(['default_formation' => $formation->value]);
    }
}

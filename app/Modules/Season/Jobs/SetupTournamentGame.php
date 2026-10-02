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
use App\Models\GamePlayerTemplate;
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

        // National team competition progression: if this is a new season
        // (game has archived seasons from previous competitions), advance to
        // the next competition in the sequence
        // (e.g. WNL → WQUEFA → WWCU27 → WOLYMP → WNL → WEUROQ → WEURO …).
        // This creates the unified multi-year calendar Izan wants.
        $hasPreviousSeason = \App\Models\SeasonArchive::where('game_id', $game->id)->exists();
        if ($hasPreviousSeason) {
            $nextCompetition = TournamentCreationService::nextCompetitionInSequence($game->competition_id, $game);
            if ($nextCompetition !== null && $nextCompetition !== $game->competition_id) {
                // Final-tournament gate: the World Cup / Euro are only
                // played when the user's side finished top 2 in its
                // qualifier. Otherwise the cycle skips the final and the
                // team goes back to its confederation's competition.
                if ($nextCompetition === TournamentCreationService::WWCU27_ID
                    && !TournamentCreationService::userQualifiedForFinal($game, $game->competition_id)) {
                    $nextCompetition = TournamentCreationService::competitionIdForConfederation(
                        $game->team?->confederation
                    );
                }
                if ($nextCompetition === TournamentCreationService::WOLYMP_ID
                    && !TournamentCreationService::userQualifiedForFinal($game, $game->competition_id)) {
                    $nextCompetition = TournamentCreationService::competitionIdForConfederation(
                        $game->team?->confederation
                    );
                }
                if ($nextCompetition === TournamentCreationService::WEURO_ID
                    && !TournamentCreationService::userQualifiedForFinal($game, TournamentCreationService::WEUROQ_ID)) {
                    $nextCompetition = TournamentCreationService::WNL_ID;
                }
                $game->update(['competition_id' => $nextCompetition]);
                // Refresh the model to use the new competition_id below.
                $game->refresh();
            }
        }

        // Women's World Cup Qualifiers (beta): the user's group is drawn at
        // setup time instead of coming from a fixed groups.json. Each FIFA
        // confederation runs its own qualifier (WQUEFA, WQAFC, ...); WWCQ is
        // the legacy alias for the original single global competition.
        // WNL (UEFA Women's Nations League) uses the real 2025 groups.
        // WWCU27 (World Cup 2027), WOLYMP (Olympics 2028) and WEURO
        // (Euro 2029) are final tournaments: drawn groups + knockout bracket.
        if (in_array($game->competition_id, TournamentCreationService::NATIONAL_TEAM_COMPETITION_IDS, true)) {
            if (in_array($game->competition_id, TournamentCreationService::NATIONAL_TEAM_FINAL_IDS, true)) {
                $this->handleNationalFinalTournament($game, $notificationService, $formationRecommender, $formationBiasResolver);
                return;
            }
            $this->handleNationalQualifiers($game, $notificationService, $formationRecommender, $formationBiasResolver);
            return;
        }

        // Anti-legacy guard: a national-team game must never fall through to
        // the retired men's WC2026 path below. Fail loudly instead.
        $setupTeam = Team::find($this->teamId);
        if ($setupTeam !== null && $setupTeam->type === 'national') {
            throw new \RuntimeException(
                "SetupTournamentGame: unknown national-team competition '{$game->competition_id}' — refusing the legacy WC2026 path."
            );
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

            // Seed the fictional newsroom for the in-game social network.
            $freshGame = Game::find($this->gameId);
            if ($freshGame) {
                app(\App\Modules\Media\Services\JournalistService::class)->seedFor($freshGame);
            }

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
     * Final-tournament formats: group count, host nation (FIFA code,
     * always included — Brazil 2027, USA 2028, Germany 2029) and the
     * confederation the rivals are drawn from (null = worldwide, for
     * the World Cup and the Olympics).
     */
    private const FINAL_TOURNAMENT_FORMATS = [
        'WWCU27' => ['groups' => 8, 'host_fifa_code' => 'BRA', 'rival_confederation' => null],
        'WOLYMP' => ['groups' => 3, 'host_fifa_code' => 'USA', 'rival_confederation' => null],
        'WEURO'  => ['groups' => 4, 'host_fifa_code' => 'GER', 'rival_confederation' => 'UEFA'],
    ];

    /**
     * Setup for the women's final tournaments: WWCU27 (FIFA Women's
     * World Cup 2027, hosted by Brazil), WOLYMP (Olympic women's
     * football 2028, hosted by the USA) and WEURO (UEFA Women's Euro
     * 2029, hosted by Germany).
     *
     * Draws groups of 4 (8 for the World Cup, 3 for the Olympics, 4 for
     * the Euros), creates a single round-robin group stage with group
     * labels, and leaves the knockout bracket to GroupStageCupHandler,
     * which generates it progressively from data/2026/{id}/bracket.json
     * once the groups are decided. The host nation is always in the draw.
     */
    private function handleNationalFinalTournament(
        Game $game,
        NotificationService $notificationService,
        FormationRecommender $formationRecommender,
        FormationBiasResolver $formationBiasResolver,
    ): void {
        DB::transaction(function () use ($game, $notificationService, $formationRecommender, $formationBiasResolver) {
            $competitionId = $game->competition_id;

            // Step 1: draw the groups (host nation guaranteed in)
            $groups = $this->drawFinalGroups($game, $competitionId);
            $allTeamIds = array_merge(...array_values($groups));

            // Step 2: competition entries
            $this->createQualifierEntries($competitionId, $allTeamIds);

            // Step 3: game players (user's 23 + AI rosters)
            $this->createQualifierPlayers($game, $allTeamIds);

            // Step 4: group-stage fixtures (3 matchdays, single round-robin)
            $this->createFinalGroupFixtures($competitionId, $groups);

            // Step 5: standings WITH group labels (the KO generator needs them)
            $this->createFinalGroupStandings($competitionId, $groups);

            // Step 6: default formation for the user's squad
            $this->setUserTeamDefaultFormation($formationRecommender, $formationBiasResolver);

            // Welcome notification
            $teamName = Team::find($this->teamId)?->getRawOriginal('name') ?? '';
            $notificationService->notifyTournamentWelcome($game, $competitionId, $teamName);

            // Mark setup as complete
            Game::where('id', $this->gameId)->update(['setup_completed_at' => now()]);

            // Record activation event
            app(\App\Modules\Season\Services\ActivationTracker::class)
                ->record($game->user_id, \App\Models\ActivationEvent::EVENT_SETUP_COMPLETED, $this->gameId, Game::MODE_TOURNAMENT);
        });
    }

    /**
     * Draw the final-tournament groups: the user's team headlines group
     * A; the host nation (Brazil 2027 / USA 2028 / Germany 2029) is
     * always included; the rest are drawn (UEFA-only for the Euros,
     * worldwide for the World Cup and the Olympics), preferring sides
     * with a playable roster.
     *
     * @return array<string, array<int, string>> group label → team ids
     */
    private function drawFinalGroups(Game $game, string $competitionId): array
    {
        $format = self::FINAL_TOURNAMENT_FORMATS[$competitionId]
            ?? ['groups' => 8, 'host_fifa_code' => null, 'rival_confederation' => null];

        $numGroups = $format['groups'];
        $neededRivals = $numGroups * 4 - 1;

        // Host nation always qualifies (unless the user IS the host).
        $guaranteed = [];
        if ($format['host_fifa_code'] !== null) {
            $hostId = Team::where('type', 'national')
                ->where('fifa_code', $format['host_fifa_code'])
                ->value('id');
            if ($hostId && $hostId !== $this->teamId) {
                $guaranteed[] = $hostId;
            }
        }

        $rivals = $this->drawFinalRivals($game, $neededRivals - count($guaranteed), $format['rival_confederation'], $guaranteed);
        $rivals = array_merge($guaranteed, $rivals);
        shuffle($rivals);

        $groups = ['A' => array_merge([$this->teamId], array_slice($rivals, 0, 3))];
        $rest = array_slice($rivals, 3);
        $labels = range('B', 'Z');
        for ($i = 1; $i < $numGroups; $i++) {
            $groups[$labels[$i - 1]] = array_values(array_slice($rest, ($i - 1) * 4, 4));
        }

        return $groups;
    }

    /**
     * Draw $count final-tournament opponents, preferring sides with a
     * playable roster (≥18 templated players for the game's season).
     *
     * @param string|null $confederation Restrict the draw to this FIFA
     *        confederation (WEURO), or null for the worldwide draw (WWCU27).
     * @param array<int, string> $excludeIds Team ids to leave out of the draw
     *        (e.g. the already-guaranteed host nation).
     */
    private function drawFinalRivals(Game $game, int $count, ?string $confederation, array $excludeIds = []): array
    {
        if ($count <= 0) {
            return [];
        }

        $candidatesQuery = fn () => Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->where('id', '!=', $this->teamId)
            ->whereNotNull('fifa_code')
            ->when(!empty($excludeIds), fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->when($confederation !== null, fn ($query) => $query->where('confederation', $confederation));

        $candidates = $candidatesQuery()->inRandomOrder()->limit(max($count * 6, 30))->pluck('id');

        $withRosters = DB::table('game_player_templates')
            ->where('season', $game->season)
            ->whereIn('team_id', $candidates)
            ->groupBy('team_id')
            ->havingRaw('COUNT(*) >= 18')
            ->pluck('team_id')
            ->shuffle()
            ->take($count)
            ->all();

        if (count($withRosters) < $count) {
            $extra = $candidatesQuery()
                ->whereNotIn('id', $withRosters)
                ->inRandomOrder()
                ->limit($count - count($withRosters))
                ->pluck('id')
                ->all();
            $withRosters = array_merge($withRosters, $extra);
        }

        return $withRosters;
    }

    /**
     * Create the group-stage fixtures: 3 matchdays of single
     * round-robin per group, dated from the competition's schedule.json.
     */
    private function createFinalGroupFixtures(string $competitionId, array $groups): void
    {
        if (GameMatch::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $dates = ['2027-06-24', '2027-06-29', '2027-07-04'];
        $schedulePath = base_path("data/2026/{$competitionId}/schedule.json");
        if (file_exists($schedulePath)) {
            $leagueDates = array_column(json_decode(file_get_contents($schedulePath), true)['league'] ?? [], 'date');
            if (count($leagueDates) >= 3) {
                $dates = array_slice($leagueDates, 0, 3);
            }
        }

        // Single round-robin pairings for 4 teams [0,1,2,3].
        $rounds = [
            [[0, 3], [1, 2]],
            [[0, 2], [3, 1]],
            [[0, 1], [2, 3]],
        ];

        $rows = [];
        foreach ($groups as $label => $teamIds) {
            foreach ($rounds as $roundIndex => $pairs) {
                foreach ($pairs as [$a, $b]) {
                    if (!isset($teamIds[$a], $teamIds[$b])) {
                        continue;
                    }
                    $rows[] = [
                        'id' => Str::uuid()->toString(),
                        'game_id' => $this->gameId,
                        'competition_id' => $competitionId,
                        'round_number' => $roundIndex + 1,
                        'round_name' => __('game.group_stage') . ' ' . $label . ' · ' . __('game.matchday') . ' ' . ($roundIndex + 1),
                        'home_team_id' => $teamIds[$a],
                        'away_team_id' => $teamIds[$b],
                        'scheduled_date' => $dates[$roundIndex],
                        'played' => false,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            GameMatch::insert($chunk);
        }
    }

    /**
     * Initialise the group standings WITH group labels — the knockout
     * generator resolves qualifiers and bracket slots from them.
     */
    private function createFinalGroupStandings(string $competitionId, array $groups): void
    {
        if (GameStanding::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $rows = [];
        foreach ($groups as $label => $teamIds) {
            $position = 1;
            foreach ($teamIds as $teamId) {
                $rows[] = [
                    'game_id' => $this->gameId,
                    'competition_id' => $competitionId,
                    'group_label' => $label,
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

        foreach (array_chunk($rows, 100) as $chunk) {
            GameStanding::insert($chunk);
        }
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
            return $this->drawQualifierGroup($game, 4);
        }

        $groupsData = json_decode(file_get_contents($groupsPath), true);
        $userFifaCode = Team::where('id', $this->teamId)->value('fifa_code');

        if (!$userFifaCode) {
            return $this->drawQualifierGroup($game, 4);
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
            return $this->drawQualifierGroup($game, 4);
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
            return $this->drawQualifierGroup($game, 4);
        }

        return $groupTeamIds;
    }

    /**
     * Draw the qualifier group: the user's national team + rivals.
     *
     * v1 format — confederation groups: rivals are drawn from the user's own
     * FIFA confederation, so a UEFA side faces UEFA sides. Falls back to the
     * legacy global draw when the confederation pool can't fill the group
     * (too few sides with ≥18 templated players, or the user's team has no
     * confederation set).
     *
     * $groupSize exists for the WNL fallback: the Nations League plays
     * 4-team groups (6 matchdays), so a UEFA side outside the real League A
     * groups gets a drawn group of 4 — the 6-team qualifier format needs
     * 10 matchdays and would blow up fixture generation.
     */
    private function drawQualifierGroup(Game $game, int $groupSize = 6): array
    {
        $rivalCount = $groupSize - 1;

        // Tolerant read: if teams.confederation doesn't exist yet (migration
        // not applied in this environment), fall back to the global draw.
        $confederation = Schema::hasColumn('teams', 'confederation')
            ? Team::where('id', $this->teamId)->value('confederation')
            : null;

        if ($confederation !== null) {
            $rivals = $this->drawRivals($game, $confederation, $rivalCount);
            if (count($rivals) === $rivalCount) {
                return array_merge([$this->teamId], $rivals);
            }
        }

        return array_merge([$this->teamId], $this->drawRivals($game, null, $rivalCount));
    }

    /**
     * Draw up to $count random opponents, preferring sides with a playable
     * roster (≥18 templated players for the game's season).
     *
     * @param string|null $confederation Restrict the draw to this FIFA
     *        confederation, or null for the legacy global draw.
     */
    private function drawRivals(Game $game, ?string $confederation, int $count = 5): array
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
            ->take($count)
            ->all();

        if (count($withRosters) < $count) {
            $extra = $candidatesQuery()
                ->whereNotIn('id', $withRosters)
                ->inRandomOrder()
                ->limit($count - count($withRosters))
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
     * their team, full templated rosters for the AI opponents.
     *
     * Idempotent per team: seasons after the first one reuse the same
     * game row, so a new drawn group only creates players for teams that
     * don't have any yet. The user's existing squad carries over between
     * seasons instead of being re-picked.
     *
     * Templates are only seeded for the game's first season while the
     * season string advances ('2026' → '2027' → …), so the queries fall
     * back to the latest season that actually has templates.
     */
    private function createQualifierPlayers(Game $game, array $groupTeamIds): void
    {
        $templateSeason = GamePlayerTemplate::where('season', $game->season)->exists()
            ? $game->season
            : GamePlayerTemplate::max('season');

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

        // AI rosters: only for teams without players in this game yet (a
        // freshly drawn group brings new opponents each season).
        $teamsWithPlayers = GamePlayer::where('game_id', $this->gameId)
            ->whereIn('team_id', $groupTeamIds)
            ->distinct()
            ->pluck('team_id')
            ->all();

        $aiTeamIds = array_values(array_diff($groupTeamIds, [$this->teamId], $teamsWithPlayers));

        if ($aiTeamIds !== []) {
            $placeholders = implode(',', array_fill(0, count($aiTeamIds), '?'));
            DB::insert(
                $columns . " WHERE t.season = ? AND t.team_id IN ($placeholders) ON CONFLICT (game_id, player_id) DO NOTHING",
                [$this->gameId, $templateSeason, ...$aiTeamIds]
            );
        }

        // User's team: only the called-up players. An existing squad
        // carries over from the previous season untouched.
        $hasSquad = GamePlayer::where('game_id', $this->gameId)
            ->where('team_id', $this->teamId)
            ->where('is_squad_member', true)
            ->exists();

        if (!$hasSquad) {
            // Fall back to the full templated roster if no squad was stored (legacy games).
            $squadPlayerIds = $game->national_squad_player_ids ?? [];

            if ($squadPlayerIds !== []) {
                $placeholders = implode(',', array_fill(0, count($squadPlayerIds), '?'));
                DB::insert(
                    $columns . " WHERE t.season = ? AND t.team_id = ? AND t.player_id IN ($placeholders) ON CONFLICT (game_id, player_id) DO NOTHING",
                    [$this->gameId, $templateSeason, $this->teamId, ...$squadPlayerIds]
                );
            } else {
                DB::insert(
                    $columns . ' WHERE t.season = ? AND t.team_id = ? ON CONFLICT (game_id, player_id) DO NOTHING',
                    [$this->gameId, $templateSeason, $this->teamId]
                );
            }
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
        SQL, [$templateSeason, $this->gameId]);
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

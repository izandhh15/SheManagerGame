<?php

namespace App\Modules\Season\Services;

use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Player\Services\PlayerValuationService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Player\Services\InjuryService;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;
use Ramsey\Uuid\Uuid;

class GamePlayerTemplateService
{
    /** @var array<string, list<string>> Transfermarkt ID → secondary positions */
    private ?array $secondaryPositionsMap = null;

    /** @var array<string, string>|null Transfermarkt ID → Sofascore ID */
    private ?array $sofascoreIdMap = null;

    /** @var array<string, array<string, string>> Season → (Transfermarkt ID → FC26 ID) */
    private array $fc26IdMaps = [];

    public function __construct(
        private ContractService $contractService,
        private PlayerDevelopmentService $developmentService,
        private PlayerValuationService $valuationService,
    ) {}

    /**
     * Delete all templates for a season (call once before generating for multiple countries).
     */
    public function clearTemplates(string $season): void
    {
        DB::table('game_player_templates')
            ->where('season', $season)
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->delete();
    }

    /**
     * Delete templates for a specific country's teams only.
     */
    public function clearTemplatesForCountry(string $season, string $countryCode): void
    {
        DB::table('game_player_templates')
            ->where('season', $season)
            ->whereIn('team_id', function ($query) use ($countryCode) {
                $query->select('id')->from('teams')->where('country', $countryCode);
            })
            ->delete();
    }

    /**
     * Delete templates for national teams (World Cup rosters).
     */
    public function clearTemplatesForNationalTeams(string $season): void
    {
        DB::table('game_player_templates')
            ->where('season', $season)
            ->whereIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->delete();
    }

    /**
     * Generate pre-computed templates for World Cup national team rosters.
     *
     * @return int Number of template rows generated
     */
    public function generateForWorldCup(string $season = '2025'): int
    {
        $this->clearTemplatesForNationalTeams($season);
        // Wipe any satellite rows that the buggy initial implementation may
        // have attached to non-national templates. Those rows survived
        // `clearTemplatesForNationalTeams` and tripped the unique constraint
        // on re-runs.
        $this->clearOrphanTournamentInfo();

        $basePath = base_path('data/2025/WC2026/teams');

        // Load national teams with roster files
        $nationalTeams = Team::where('type', 'national')
            ->whereNotNull('transfermarkt_id')
            ->get(['id', 'transfermarkt_id']);

        // Load roster data per team and collect needed transfermarkt IDs
        $teamRosters = [];

        foreach ($nationalTeams as $team) {
            $filePath = "{$basePath}/{$team->transfermarkt_id}.json";
            if (!file_exists($filePath)) {
                continue;
            }

            $data = json_decode(file_get_contents($filePath), true);
            if (!$data || empty($data['players'])) {
                continue;
            }

            $teamRosters[] = ['team_id' => $team->id, 'players' => $data['players']];
        }

        $processedPlayerIds = [];
        $rows = [];
        $tournamentInfoByPlayerId = [];

        foreach ($teamRosters as $roster) {
            foreach ($roster['players'] as $playerData) {
                // National-team World Cup rosters are not club contracts, so
                // they carry no release clause (null country → no clause).
                $row = $this->prepareTemplateRow($season, $roster['team_id'], null, $playerData, 0);
                if ($row && !isset($processedPlayerIds[$row['player_id']])) {
                    $row['number'] = null; // WC templates must not store squad numbers
                    $rows[] = $row;
                    $processedPlayerIds[$row['player_id']] = true;
                    $tournamentInfoByPlayerId[$row['player_id']] = [
                        'club_name' => $playerData['club']['name'] ?? null,
                        'club_crest_url' => $playerData['club']['image'] ?? null,
                        'is_injured' => (bool) ($playerData['injured'] ?? false),
                        'is_called_up' => (bool) ($playerData['calledUp'] ?? false),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        $this->upsertTournamentInfo($season, $tournamentInfoByPlayerId, $nationalTeams->pluck('id')->all());

        return count($rows);
    }

    /**
     * Generate pre-computed templates for ALL women's national teams (beta).
     *
     * Reads data/{season}/NAT.json: 198 national teams with their players.
     * Each player gets one template per national team they are eligible for
     * (deduplicated by player_id + team_id — the same real-world player may
     * also have a club template, which is kept separate).
     *
     * National-team templates are excluded from career-mode game setup
     * (SetupNewGame filters type='national'), so they only materialise in
     * national-team games. Players with no club template become free agents
     * in career mode via SetupNewGame::createFreeAgentsFromNationalTemplates.
     *
     * @return int Number of template rows generated
     */
    public function generateForNationalTeams(string $season = '2026'): int
    {
        $this->clearTemplatesForNationalTeams($season);

        $path = base_path("data/{$season}/NAT.json");
        if (!file_exists($path)) {
            return 0;
        }

        $data = json_decode(file_get_contents($path), true);
        $clubs = $data['clubs'] ?? [];

        $teamIdsByFifa = Team::where('type', 'national')
            ->whereNotNull('fifa_code')
            ->pluck('id', 'fifa_code')
            ->all();

        $rows = [];
        /** @var array<string, true> "$playerId|$teamId" already templated */
        $seen = [];

        foreach ($clubs as $club) {
            $fifaCode = $club['fifa_code'] ?? null;
            $teamId = $fifaCode ? ($teamIdsByFifa[$fifaCode] ?? null) : null;
            if (!$teamId) {
                continue;
            }

            foreach ($club['players'] ?? [] as $playerData) {
                $row = $this->prepareTemplateRow($season, $teamId, null, $playerData, 0);
                if (!$row) {
                    continue;
                }
                $key = $row['player_id'] . '|' . $teamId;
                if (isset($seen[$key])) {
                    continue;
                }
                // NT templates must not carry squad numbers (the partial
                // unique index on (season, team_id, number) is per team, but
                // numbers here are meaningless for call-up pools).
                $row['number'] = null;
                $rows[] = $row;
                $seen[$key] = true;
            }
        }

        // Backfill: many nations (82) list no new players in NAT.json because
        // all their internationals were already in the club datasets. Clone
        // their club templates as NT templates so every eligible player is
        // callable — including dual nationals (one template per NT).
        $rows = array_merge($rows, $this->backfillNationalTemplatesFromClubs($season, $clubs, $teamIdsByFifa, $seen));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Clone club templates as national-team templates for players whose
     * nationality matches an NT but who have no NT template yet.
     *
     * @param array<string, string> $teamIdsByFifa fifa_code → team UUID
     * @param array<string, true> $seen "$playerId|$teamId" already templated (mutated)
     * @return array<int, array<string, mixed>>
     */
    private function backfillNationalTemplatesFromClubs(string $season, array $clubs, array $teamIdsByFifa, array &$seen): array
    {
        $teamIdByName = [];
        foreach ($clubs as $club) {
            $teamId = $teamIdsByFifa[$club['fifa_code'] ?? ''] ?? null;
            if ($teamId && isset($club['name'])) {
                $teamIdByName[$club['name']] = $teamId;
            }
        }

        if ($teamIdByName === []) {
            return [];
        }

        $clubTemplates = DB::table('game_player_templates')
            ->where('season', $season)
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->get();

        $rows = [];
        foreach ($clubTemplates as $template) {
            $nationalities = json_decode($template->nationality ?? '[]', true) ?: [];
            foreach ($nationalities as $nationality) {
                $ntTeamId = $teamIdByName[$nationality] ?? null;
                if (!$ntTeamId) {
                    continue;
                }
                $key = $template->player_id . '|' . $ntTeamId;
                if (isset($seen[$key])) {
                    continue;
                }
                $row = (array) $template;
                unset($row['id']);
                $row['team_id'] = $ntTeamId;
                $row['number'] = null;
                $rows[] = $row;
                $seen[$key] = true;
            }
        }

        return $rows;
    }

    /**
     * Upsert satellite tournament-info rows keyed by the templates we just
     * inserted. Filters by national-team team_ids because the same real-world
     * player can also have a club-team template in the same season — without
     * the filter, the lookup may resolve to the club template, attach the
     * satellite there, and that orphan row then survives subsequent
     * `clearTemplatesForNationalTeams` calls and trips the unique constraint
     * on re-run.
     */
    private function upsertTournamentInfo(string $season, array $tournamentInfoByPlayerId, array $nationalTeamIds): void
    {
        if (empty($tournamentInfoByPlayerId) || empty($nationalTeamIds)) {
            return;
        }

        $templateIdsByPlayerId = DB::table('game_player_templates')
            ->where('season', $season)
            ->whereIn('team_id', $nationalTeamIds)
            ->whereIn('player_id', array_keys($tournamentInfoByPlayerId))
            ->pluck('id', 'player_id');

        $satelliteRows = [];
        foreach ($tournamentInfoByPlayerId as $playerId => $info) {
            $templateId = $templateIdsByPlayerId[$playerId] ?? null;
            if (!$templateId) {
                continue;
            }

            // Skip rows with no useful data so the satellite stays small.
            if (!$info['is_injured'] && !$info['is_called_up'] && !$info['club_name'] && !$info['club_crest_url']) {
                continue;
            }

            $satelliteRows[] = [
                'game_player_template_id' => $templateId,
                'is_injured' => $info['is_injured'],
                'is_called_up' => $info['is_called_up'],
                'club_name' => $info['club_name'],
                'club_crest_url' => $info['club_crest_url'],
            ];
        }

        foreach (array_chunk($satelliteRows, 500) as $chunk) {
            DB::table('game_player_template_tournament_info')
                ->upsert(
                    $chunk,
                    ['game_player_template_id'],
                    ['is_injured', 'is_called_up', 'club_name', 'club_crest_url'],
                );
        }
    }

    /**
     * Delete satellite rows that point to templates which aren't national
     * teams. Tournament info is only meaningful on national-team templates,
     * so any other row is an orphan from the buggy initial implementation
     * that resolved player_id to an ambiguous template id.
     */
    private function clearOrphanTournamentInfo(): void
    {
        DB::table('game_player_template_tournament_info')
            ->whereIn('game_player_template_id', function ($query) {
                $query->select('id')
                    ->from('game_player_templates')
                    ->whereNotIn('team_id', function ($inner) {
                        $inner->select('id')->from('teams')->where('type', 'national');
                    });
            })
            ->delete();
    }

    /**
     * Generate pre-computed game_player_templates for a season and country.
     * Additive — call clearTemplates() first if a fresh start is needed.
     *
     * @return int Number of template rows generated
     */
    public function generateTemplates(string $season, string $countryCode): int
    {
        $allTeamIds = Team::whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        // team_id → country (uppercase 2-char) map, resolved once so the
        // mandatory-release-clause seed can be derived per template without an
        // N+1 Team load. A single generateTemplates() run spans clubs from
        // multiple countries (continental/Swiss opponents), so the per-team
        // country — not the $countryCode argument — drives the ES predicate.
        $teamCountries = Team::whereNotNull('transfermarkt_id')
            ->pluck('country', 'id')
            ->toArray();

        $countryConfig = app(CountryConfig::class);
        $competitionIds = $countryConfig->playerInitializationOrder($countryCode);
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);
        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);

        $totalCount = 0;

        // Track already-processed club teams (including from prior country runs)
        // Exclude national teams so their players can still get club templates
        $nationalTeamIds = Team::where('type', 'national')->pluck('id');

        $processedTeamIds = DB::table('game_player_templates')
            ->where('season', $season)
            ->whereNotIn('team_id', $nationalTeamIds)
            ->distinct()
            ->pluck('team_id')
            ->flip()
            ->toArray();

        // Track already-processed players to avoid duplicates across club teams
        $processedPlayerIds = DB::table('game_player_templates')
            ->where('season', $season)
            ->whereNotIn('team_id', $nationalTeamIds)
            ->distinct()
            ->pluck('player_id')
            ->flip()
            ->toArray();

        // Second pass: generate template rows
        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
                continue;
            }

            $rows = $this->generateForCompetition($competitionId, $season, $allTeamIds, $teamCountries, $processedTeamIds, $processedPlayerIds);
            $totalCount += $this->insertAndTrack($rows, $processedTeamIds, $processedPlayerIds);
        }

        foreach ($swissIds as $competitionId) {
            $rows = $this->generateForSwissGapTeams($competitionId, $season, $allTeamIds, $teamCountries, $processedTeamIds, $processedPlayerIds);
            $totalCount += $this->insertAndTrack($rows, $processedTeamIds, $processedPlayerIds);
        }

        return $totalCount;
    }

    private function insertAndTrack(array $rows, array &$processedTeamIds, array &$processedPlayerIds): int
    {
        foreach ($rows as $row) {
            $processedTeamIds[$row['team_id']] = true;
            $processedPlayerIds[$row['player_id']] = true;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Generate template rows for a non-continental competition.
     */
    private function generateForCompetition(
        string $competitionId,
        string $season,
        array $allTeamIds,
        array $teamCountries = [],
        array $processedTeamIds = [],
        array $processedPlayerIds = [],
    ): array {
        $basePath = base_path("data/{$season}/{$competitionId}");
        $teamsFilePath = "{$basePath}/teams.json";

        if (file_exists($teamsFilePath)) {
            $clubs = $this->loadClubsFromTeamsJson($teamsFilePath);
        } else {
            $clubs = $this->loadClubsFromTeamPoolFiles($basePath);
        }

        if (empty($clubs)) {
            return [];
        }

        $rows = [];

        foreach ($clubs as $club) {
            $transfermarktId = $club['transfermarktId'] ?? $this->extractTransfermarktIdFromImage($club['image'] ?? '');
            if (!$transfermarktId) {
                continue;
            }

            $teamId = $allTeamIds[$transfermarktId] ?? null;
            if (!$teamId) {
                continue;
            }

            // Skip teams already processed by a prior country run
            if (isset($processedTeamIds[$teamId])) {
                continue;
            }

            $minimumWage = $this->contractService->getMinimumWageForCompetition($competitionId, $teamId);
            $clubCountry = $teamCountries[$teamId] ?? null;

            foreach ($club['players'] ?? [] as $playerData) {
                $playerData = $this->applyMarketValueFallback($playerData, $competitionId);
                $row = $this->prepareTemplateRow($season, $teamId, $clubCountry, $playerData, $minimumWage);
                if ($row && !isset($processedPlayerIds[$row['player_id']])) {
                    $rows[] = $row;
                    $processedPlayerIds[$row['player_id']] = true;
                }
            }
        }

        return $rows;
    }

    /**
     * Primera RFEF (ESP3A / ESP3B) source data has sporadic missing market values.
     * Treat any missing/empty value as €50k so wage calculation and tiering stay sane.
     */
    private function applyMarketValueFallback(array $playerData, string $competitionId): array
    {
        if (!in_array($competitionId, ['ESP3A', 'ESP3B'], true)) {
            return $playerData;
        }

        if (empty($playerData['marketValue']) || $playerData['marketValue'] === '-') {
            $playerData['marketValue'] = '€50k';
        }

        return $playerData;
    }

    /**
     * Generate template rows for Swiss format gap teams (teams not already processed).
     */
    private function generateForSwissGapTeams(
        string $competitionId,
        string $season,
        array $allTeamIds,
        array $teamCountries,
        array $processedTeamIds,
        array $processedPlayerIds = [],
    ): array {
        $teamsFilePath = base_path("data/{$season}/{$competitionId}/teams.json");
        if (!file_exists($teamsFilePath)) {
            return [];
        }

        $teamsData = json_decode(file_get_contents($teamsFilePath), true);
        $clubs = $teamsData['clubs'] ?? [];
        $rows = [];

        foreach ($clubs as $club) {
            $transfermarktId = $club['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $teamId = $allTeamIds[$transfermarktId] ?? null;
            if (!$teamId) {
                continue;
            }

            // Skip teams already processed from tier/pool competitions
            if (isset($processedTeamIds[$teamId])) {
                continue;
            }

            $minimumWage = $this->contractService->getMinimumWageForCompetition($competitionId, $teamId);
            $clubCountry = $teamCountries[$teamId] ?? null;

            foreach ($club['players'] ?? [] as $playerData) {
                $row = $this->prepareTemplateRow($season, $teamId, $clubCountry, $playerData, $minimumWage);
                if ($row && !isset($processedPlayerIds[$row['player_id']])) {
                    $rows[] = $row;
                    $processedPlayerIds[$row['player_id']] = true;
                }
            }
        }

        return $rows;
    }

    /**
     * Prepare a single template row from player JSON data.
     * Mirrors SetupNewGame::prepareGamePlayerRow() but stores season instead of game_id.
     */
    private function prepareTemplateRow(
        string $season,
        string $teamId,
        ?string $clubCountry,
        array $playerData,
        int $minimumWage,
    ): ?array {
        if (empty($playerData['id'])) {
            return null;
        }

        $dateOfBirth = null;
        if (!empty($playerData['dateOfBirth'])) {
            try {
                $dateOfBirth = Carbon::parse($playerData['dateOfBirth']);
            } catch (\Exception $e) {
                // Invalid date — biography stays partial
            }
        }
        if ($dateOfBirth === null) {
            // No verified DOB (common for Liga MX Femenil and other leagues
            // where Soccerdonna lacks birth dates): use a deterministic,
            // varied placeholder per player instead of a flat 2000-01-01,
            // so squads don't show every unknown as exactly 26 years old.
            $dateOfBirth = self::variedDefaultDob((string) ($playerData['id'] ?? $playerData['name'] ?? ''), $season);
        }

        $referenceDate = Carbon::parse("{$season}-08-15");
        $contractUntil = self::variedDefaultContract((string) ($playerData['id'] ?? $playerData['name'] ?? ''), $referenceDate);

        if (!empty($playerData['contract']) && $playerData['contract'] !== '-') {
            try {
                $parsed = Carbon::parse($playerData['contract']);
                $year = $parsed->month > 6 ? $parsed->year + 1 : $parsed->year;
                $candidate = Carbon::createFromDate($year, 6, 30);

                if ($candidate->greaterThan($referenceDate)) {
                    $contractUntil = $candidate->toDateString();
                }
            } catch (\Exception $e) {
                // Invalid date — keep default
            }
        }

        $age = (int) $dateOfBirth->diffInYears($referenceDate);
        $position = $playerData['position'] ?? null;
        $explicitOverall = $this->resolveExplicitAbility($playerData['overall_score'] ?? null);
        $marketValueCents = Money::parseMarketValue($playerData['marketValue'] ?? null);
        $marketValueLabel = $playerData['marketValue'] ?? null;
        if ($marketValueCents <= 0 && $explicitOverall !== null) {
            // Mas media -> mas valor: con rating FIFA/heuristico explicito y sin
            // valor de mercado real, el valor se deriva de la media con la curva
            // inversa del juego en vez del suelo plano de 25K.
            $marketValueCents = $this->valuationService->overallScoreToMarketValue($explicitOverall, $age, null, $position);
            $marketValueLabel = Money::format($marketValueCents);
        }
        // Transfermarkt occasionally lists fringe / youth squad players with no
        // quoted value. Floor those at €25K so they get a usable ability
        // baseline, a non-zero transfer price, and don't render as "Free" in the
        // market. With the women's-economy anchors €25K maps to ~50 raw
        // (~58 with the default competence-floor blend) — a competent
        // unknown, not the ~63-66 a €100K floor would now imply. Flooring the
        // persisted value (not just an ability-only local) keeps stored market
        // value, wage, release clause and tier consistent.
        if ($marketValueCents <= 0) {
            $marketValueCents = 2_500_000; // €25K, matches PlayerGeneratorService floor
        }
        $overallScore = $explicitOverall
            ?? $this->valuationService->marketValueToOverallScore($marketValueCents, $age, $position);
        $annualWage = $this->contractService->calculateAnnualWageForPlayer($overallScore, $marketValueCents, $minimumWage, $age, $position);

        $explicitPotential = $this->resolveExplicitPotential($playerData['potential'] ?? null, $overallScore);
        $potentialData = $explicitPotential !== null
            ? $this->developmentService->scoutedRangeForKnownPotential($age, $overallScore, $explicitPotential)
            : $this->developmentService->generatePotential($age, $overallScore);

        $secondaryPositions = $this->getSecondaryPositions($playerData['id']);

        $foot = match (strtolower($playerData['foot'] ?? '')) {
            'left' => 'left',
            'right' => 'right',
            'both' => 'both',
            default => null,
        };

        return [
            'season' => $season,
            // Deterministic UUID per transfermarkt_id so the same real-world
            // player gets a stable player_id across (season, team) templates
            // and (game_id, player_id) dedups correctly when SetupNewGame
            // copies templates into game_players.
            'player_id' => self::playerIdFor((string) $playerData['id']),
            'transfermarkt_id' => (string) $playerData['id'],
            'sofascore_id' => $this->getSofascoreId((string) $playerData['id']),
            'fc26_id' => $this->getFc26Id($season, (string) $playerData['id']),
            'name' => $playerData['name'] ?? null,
            'date_of_birth' => $dateOfBirth->toDateString(),
            'nationality' => isset($playerData['nationality']) ? json_encode($playerData['nationality']) : null,
            'height' => $playerData['height'] ?? null,
            'foot' => $foot,
            'team_id' => $teamId,
            // Owning club's Transfermarkt id when the player is on loan. He is
            // listed in the borrowing club's squad (= $teamId), so this points
            // at loan.from; SetupNewGame materialises it into a loan record.
            'loan_from_transfermarkt_id' => isset($playerData['loan']['from']['id'])
                ? (string) $playerData['loan']['from']['id']
                : null,
            // A squad file states an unknown shirt as "" (or omits it). That has
            // to land as NULL, not 0: the partial unique index on
            // (season, team_id, number) covers every non-null value, so casting
            // blanks to 0 makes two shirtless team-mates collide and
            // insertOrIgnore silently drops one. Mirrors SeasonData's guard.
            'number' => ($playerData['number'] ?? '') === '' ? null : (int) $playerData['number'],
            'position' => $position ?? 'Unknown',
            'secondary_positions' => json_encode($secondaryPositions),
            'market_value' => $marketValueLabel,
            'market_value_cents' => $marketValueCents,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            // Mandatory floor for ES clubs (= es_floor_multiplier × MV), null
            // elsewhere. Seeded at baseline wages, so the floor IS the default.
            'release_clause' => $this->contractService->calculateReleaseClause($marketValueCents, $clubCountry),
            'fitness' => 80,
            'morale' => 80,
            'durability' => InjuryService::generateDurability(),
            'overall_score' => $overallScore,
            'potential' => $potentialData['potential'],
            'potential_low' => $potentialData['low'],
            'potential_high' => $potentialData['high'],
            'tier' => PlayerTierService::tierFromMarketValue($marketValueCents),
        ];
    }

    private function loadClubsFromTeamsJson(string $teamsFilePath): array
    {
        $data = json_decode(file_get_contents($teamsFilePath), true);
        return $data['clubs'] ?? [];
    }

    private function loadClubsFromTeamPoolFiles(string $basePath): array
    {
        $clubs = [];

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data) {
                continue;
            }

            $clubs[] = [
                'image' => $data['image'] ?? '',
                'transfermarktId' => $this->extractTransfermarktIdFromImage($data['image'] ?? ''),
                'players' => $data['players'] ?? [],
            ];
        }

        return $clubs;
    }

    /**
     * Deterministic, varied placeholder DOB for players without a verified
     * birth date. Replaces the old flat 2000-01-01 default so squads don't
     * show every unknown as exactly the same age. Distribution is weighted
     * like a real squad (peak 21-30, few teenagers/veterans). Keyed by player
     * id so it's stable across seeds and matches the migration backfill.
     */
    public static function variedDefaultDob(string $playerKey, string $season): Carbon
    {
        // 7 hex digits max (0xFFFFFFF < 2^31): hexdec() with 8 digits returns
        // a float on 32-bit PHP and the % below can go negative (fatal month).
        $h = hexdec(substr(md5('dob:' . $playerKey), 0, 7));
        $r = ($h % 1000) / 1000.0;
        $age = match (true) {
            $r < 0.08 => 18 + ($h % 3),   // 18-20
            $r < 0.35 => 21 + ($h % 3),   // 21-23
            $r < 0.75 => 24 + ($h % 7),   // 24-30
            $r < 0.92 => 31 + ($h % 3),   // 31-33
            default => 34 + ($h % 2),     // 34-35
        };
        $refYear = (int) Carbon::parse("{$season}-08-15")->year;

        return Carbon::createFromDate($refYear - $age, 1 + ($h % 12), 1 + (($h >> 5) % 28))->startOfDay();
    }

    /**
     * Deterministic, varied default contract end for players without contract
     * data. Replaces the old flat 2027-06-30 default (1-4 seasons out).
     */
    public static function variedDefaultContract(string $playerKey, Carbon $referenceDate): string
    {
        // 7 hex digits max (0xFFFFFFF < 2^31): see variedDefaultDob.
        $h = hexdec(substr(md5('contract:' . $playerKey), 0, 7));
        $years = 1 + ($h % 4); // 1-4 years

        return $referenceDate->copy()->addYears($years)->month(6)->day(30)->toDateString();
    }

    /**
     * Deterministic player_id UUID per Transfermarkt id, so the same
     * real-world player gets a stable UUID across (season, team) templates
     * and the (game_id, player_id) unique constraint on game_players keeps
     * dedup'ing correctly when SetupNewGame copies templates over.
     */
    public static function playerIdFor(string $transfermarktId): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_OID, 'player:' . $transfermarktId)->toString();
    }

    private function extractTransfermarktIdFromImage(string $imageUrl): ?string
    {
        if (preg_match('/\/(\d+)\.png$/', $imageUrl, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Validate a hand-curated `overall_score` from source JSON.
     * Returns the clamped int, or null to signal "fall back to the heuristic".
     */
    private function resolveExplicitAbility(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1 || $value > 99) {
            return null;
        }
        return $value;
    }

    /**
     * Validate a hand-curated `potential` from source JSON. Must be a valid
     * 1–99 integer and at least the current `overall_score` — a potential
     * below current ability is meaningless and would break development math.
     */
    private function resolveExplicitPotential(mixed $raw, int $overallScore): ?int
    {
        $value = $this->resolveExplicitAbility($raw);
        if ($value === null || $value < $overallScore) {
            return null;
        }
        return $value;
    }

    /**
     * Get secondary positions for a player by Transfermarkt ID.
     *
     * @return list<string>
     */
    private function getSecondaryPositions(string $transfermarktId): array
    {
        if ($this->secondaryPositionsMap === null) {
            $this->secondaryPositionsMap = $this->loadSecondaryPositionsMap();
        }

        return $this->secondaryPositionsMap[$transfermarktId] ?? [];
    }

    /**
     * Load the secondary positions data file, keyed by Transfermarkt ID.
     *
     * @return array<string, list<string>>
     */
    private function loadSecondaryPositionsMap(): array
    {
        $files = glob(base_path('data/players/player_positions_*.json'));
        $map = [];

        foreach ($files as $file) {
            $entries = json_decode(file_get_contents($file), true);
            foreach ($entries as $entry) {
                $map[$entry['id']] = $entry['positions'] ?? [];
            }
        }

        return $map;
    }

    /**
     * Resolve a player's Sofascore ID from the Transfermarkt→Sofascore crosswalk.
     * Returns null when the player isn't covered by the crosswalk.
     */
    private function getSofascoreId(string $transfermarktId): ?string
    {
        $this->sofascoreIdMap ??= $this->loadSofascoreIdMap();

        return $this->sofascoreIdMap[$transfermarktId] ?? null;
    }

    /**
     * Load the Transfermarkt→Sofascore map, keyed by Transfermarkt ID. Both sides
     * are permanent external identifiers, so the map is deliberately NOT scoped to
     * a season — it lives at data/sofascore_ids.json and is shared by every one.
     * A per-season copy is how the 2026 refresh silently lost every player photo:
     * the folder shipped without the file and every player got a null sofascore_id.
     *
     * The file is built by `app:build-sofascore-id-map` from people.csv. A missing
     * file is still non-fatal (players keep a null sofascore_id and fall back to the
     * default avatar) but it is an operational error, not a routine case —
     * `app:validate-season` warns when squad coverage drops.
     *
     * @return array<string, string>
     */
    private function loadSofascoreIdMap(): array
    {
        $path = base_path('data/sofascore_ids.json');
        if (!file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    /**
     * Resolve a player's FC26 ID from the Transfermarkt→FC26 fuzzy-match map.
     * Returns null when the player isn't covered by the map.
     */
    private function getFc26Id(string $season, string $transfermarktId): ?string
    {
        if (!isset($this->fc26IdMaps[$season])) {
            $this->fc26IdMaps[$season] = $this->loadFc26IdMap($season);
        }

        return $this->fc26IdMaps[$season][$transfermarktId] ?? null;
    }

    /**
     * Load the Transfermarkt→FC26 map for a season, keyed by Transfermarkt ID.
     * The file is built by `app:build-fc26-id-map` from the FC26 player export;
     * a missing file is non-fatal (all players keep a null fc26_id).
     *
     * @return array<string, string>
     */
    private function loadFc26IdMap(string $season): array
    {
        $path = base_path("data/{$season}/fc26_ids.json");
        if (!file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }
}

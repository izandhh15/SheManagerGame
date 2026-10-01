<?php

use App\Modules\Player\Services\InjuryService;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Player\Services\PlayerValuationService;
use App\Modules\Season\Services\GamePlayerTemplateService;
use App\Modules\Transfer\Services\ContractService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Realistic player ratings (EA Sports FC 27), 01-10-2026.
 *
 * Applies the curated ratings from data/2026/asterisk/teams.json +
 * data/players/NAT.json (1.687 official FC 27 ratings + derived values for
 * the rest) to the seeded 2026 templates:
 *
 * - overall_score from the JSON rating (official FC 27 where available)
 * - market_value derived from the rating where the JSON had no real value
 *   (more rating -> more value), instead of the flat EUR 25K floor
 * - annual_wage / potential / tier recalculated from the new numbers
 * - varied placeholder DOBs (no more flat 2000-01-01) and varied default
 *   contracts (no more flat 2027-06-30) for players without real data
 *
 * Also moves Alexia Putellas' club template from FC Barcelona to
 * London City Lionesses (she signed there as a free agent in July 2026):
 * deletes the outdated Barcelona row created by 000002 and inserts the
 * London City one with her official FC 27 rating. Her Spain NT template
 * is left untouched.
 *
 * Performance: the refresh runs as bulk CASE-based UPDATEs (a few hundred
 * rows per statement) instead of one UPDATE per template, and JSON files
 * are processed one at a time, so it survives the Wasmer Edge PHP
 * limits (32-bit, HTTP timeouts).
 *
 * Idempotent: re-running only rewrites rows that still differ from the
 * source-of-truth JSON (potential uses a per-player rand seed).
 */
return new class extends Migration
{
    private const SEASON = '2026';

    private const FLOOR_CENTS = 2_500_000; // EUR 25K legacy floor

    public function up(): void
    {
        set_time_limit(0);

        $this->movePutellasToLondonCity();
        $this->ensurePutellasSpainTemplate();
        $this->refreshAllRatings();
    }

    public function down(): void
    {
        // Data migration: ratings cannot be rolled back automatically.
    }

    /**
     * Alexia Putellas signed for London City Lionesses as a free agent in
     * July 2026, so the FC Barcelona club template created by migration
     * 000002 is outdated: delete it and insert the London City one with
     * her official EA Sports FC 27 rating (91). Her Spain national-team
     * template is kept as is.
     *
     * Idempotent: the Barcelona row is deleted if present; the London City
     * row is inserted only when missing.
     */
    private function movePutellasToLondonCity(): void
    {
        $barcaId = DB::table('teams')
            ->where('type', '!=', 'national')
            ->where('name', 'FC Barcelona')
            ->value('id');
        $londonId = DB::table('teams')
            ->where('type', '!=', 'national')
            ->where('name', 'London City Lionesses')
            ->value('id');

        if ($barcaId) {
            DB::table('game_player_templates')
                ->where('season', self::SEASON)
                ->where('team_id', $barcaId)
                ->where('transfermarkt_id', '904001')
                ->delete();
        }

        if (!$londonId) {
            return;
        }

        $exists = DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->where('team_id', $londonId)
            ->where('transfermarkt_id', '904001')
            ->exists();
        if ($exists) {
            return;
        }

        $contracts = app(ContractService::class);
        $development = app(PlayerDevelopmentService::class);

        $playerId = GamePlayerTemplateService::playerIdFor('904001');
        $dob = Carbon::parse('1994-02-04');
        $age = (int) $dob->diffInYears(Carbon::parse(self::SEASON . '-08-15'));
        $marketValueCents = Money::parseMarketValue('€500k');
        $overall = 91; // official EA Sports FC 27 rating (London City Lionesses)
        $potential = $development->generatePotential($age, $overall, $marketValueCents);

        DB::table('game_player_templates')->insert([
            'season' => self::SEASON,
            'player_id' => $playerId,
            'transfermarkt_id' => '904001',
            'sofascore_id' => null,
            'fc26_id' => null,
            'name' => 'Alexia Putellas',
            'date_of_birth' => $dob->toDateString(),
            'nationality' => json_encode(['Spain']),
            'height' => '1,73m',
            'foot' => 'left',
            'secondary_positions' => json_encode([]),
            'market_value' => '€500k',
            'market_value_cents' => $marketValueCents,
            'contract_until' => '2028-06-30',
            'fitness' => 80,
            'morale' => 80,
            'durability' => InjuryService::generateDurability(),
            'overall_score' => $overall,
            'potential' => $potential['potential'],
            'potential_low' => $potential['low'],
            'potential_high' => $potential['high'],
            'tier' => PlayerTierService::tierFromMarketValue($marketValueCents),
            'position' => 'Midfielder',
            'team_id' => $londonId,
            'number' => 11,
            'annual_wage' => $contracts->calculateAnnualWageForPlayer($overall, $marketValueCents, 0, $age, 'Midfielder', true),
            'release_clause' => $contracts->calculateReleaseClause($marketValueCents, 'EN'),
        ]);
    }

    /**
     * The E2E found Alexia missing from Spain's call-up pool: her Spain NT
     * template may never have been inserted (migration 000002 resolved the
     * team by fifa_code). Ensure it exists with her official EA Sports FC 27
     * rating (91) so she is eligible for the convocatoria. Idempotent.
     */
    private function ensurePutellasSpainTemplate(): void
    {
        $spainId = DB::table('teams')
            ->where('type', 'national')
            ->where('fifa_code', 'ESP')
            ->value('id');

        if (!$spainId) {
            return;
        }

        $overall = 91; // official EA Sports FC 27 rating

        $exists = DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->where('team_id', $spainId)
            ->where('transfermarkt_id', '904001')
            ->exists();

        if ($exists) {
            // The row is there: make sure it carries the official rating.
            DB::table('game_player_templates')
                ->where('season', self::SEASON)
                ->where('team_id', $spainId)
                ->where('transfermarkt_id', '904001')
                ->update(['overall_score' => $overall]);

            return;
        }

        $contracts = app(ContractService::class);
        $development = app(PlayerDevelopmentService::class);

        $playerId = GamePlayerTemplateService::playerIdFor('904001');
        $dob = Carbon::parse('1994-02-04');
        $age = (int) $dob->diffInYears(Carbon::parse(self::SEASON . '-08-15'));
        $marketValueCents = Money::parseMarketValue('€500k');
        $potential = $development->generatePotential($age, $overall, $marketValueCents);

        // NT templates carry no squad number (partial unique index on
        // (season, team_id, number)); the picker is the call-up pool.
        DB::table('game_player_templates')->insert([
            'season' => self::SEASON,
            'player_id' => $playerId,
            'transfermarkt_id' => '904001',
            'sofascore_id' => null,
            'fc26_id' => null,
            'name' => 'Alexia Putellas',
            'date_of_birth' => $dob->toDateString(),
            'nationality' => json_encode(['Spain']),
            'height' => '1,73m',
            'foot' => 'left',
            'secondary_positions' => json_encode([]),
            'market_value' => '€500k',
            'market_value_cents' => $marketValueCents,
            'contract_until' => '2028-06-30',
            'fitness' => 80,
            'morale' => 80,
            'durability' => InjuryService::generateDurability(),
            'overall_score' => $overall,
            'potential' => $potential['potential'],
            'potential_low' => $potential['low'],
            'potential_high' => $potential['high'],
            'tier' => PlayerTierService::tierFromMarketValue($marketValueCents),
            'position' => 'Midfielder',
            'team_id' => $spainId,
            'number' => null,
            'annual_wage' => $contracts->calculateAnnualWageForPlayer($overall, $marketValueCents, 0, $age, 'Midfielder', true),
            'release_clause' => $contracts->calculateReleaseClause($marketValueCents, null),
        ]);
    }

    /**
     * Refresh all 2026 templates from the JSON source of truth, one JSON
     * file at a time, applying changes with bulk CASE UPDATEs.
     */
    private function refreshAllRatings(): void
    {
        $valuation = app(PlayerValuationService::class);
        $contracts = app(ContractService::class);
        $development = app(PlayerDevelopmentService::class);

        // team_id -> competition_id (for the wage minimum)
        $teamCompetition = DB::table('competition_teams')
            ->where('season', self::SEASON)
            ->pluck('competition_id', 'team_id')
            ->all();

        $reference = Carbon::parse(self::SEASON . '-08-15');
        $minWageCache = [];
        $done = []; // transfermarkt_id already processed (first file wins)

        $files = glob(base_path('data/2026/*/teams.json')) ?: [];
        $natFile = base_path('data/2026/NAT.json');
        if (is_file($natFile)) {
            $files[] = $natFile;
        }

        foreach ($files as $file) {
            $data = json_decode(@file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $clubs = $data['clubs'] ?? $data['teams'] ?? [];
            unset($data);

            $jsonPlayers = [];
            foreach ($clubs as $club) {
                foreach ($club['players'] ?? [] as $p) {
                    $tmId = (string) ($p['id'] ?? '');
                    if ($tmId === '' || isset($done[$tmId]) || isset($jsonPlayers[$tmId])) {
                        continue;
                    }
                    if (empty($p['overall_score'])) {
                        continue;
                    }
                    $jsonPlayers[$tmId] = [
                        'overall_score' => (int) $p['overall_score'],
                        'marketValue' => $p['marketValue'] ?? null,
                        'dateOfBirth' => $p['dateOfBirth'] ?? null,
                        'contract' => $p['contract'] ?? null,
                    ];
                }
            }
            unset($clubs);
            if (empty($jsonPlayers)) {
                continue;
            }
            foreach ($jsonPlayers as $tmId => $_) {
                $done[$tmId] = true;
            }

            $updates = [];
            $me = $this;
            DB::table('game_player_templates')
                ->where('season', self::SEASON)
                ->whereIn('transfermarkt_id', array_keys($jsonPlayers))
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($me, $jsonPlayers, $valuation, $contracts, $development, $teamCompetition, $reference, &$minWageCache, &$updates) {
                    foreach ($rows as $row) {
                        $json = $jsonPlayers[(string) $row->transfermarkt_id] ?? null;
                        if (!$json) {
                            continue;
                        }
                        $u = $me->computeUpdates($row, $json, $valuation, $contracts, $development, $teamCompetition, $reference, $minWageCache);
                        if (!empty($u)) {
                            $updates[$row->id] = $u;
                        }
                    }
                });

            $this->bulkUpdate($updates);
            unset($jsonPlayers, $updates);
            gc_collect_cycles();
        }
    }

    /**
     * Compute the column updates for one template row. Returns [] when the
     * row already matches the JSON source of truth.
     */
    private function computeUpdates(
        object $row,
        array $json,
        PlayerValuationService $valuation,
        ContractService $contracts,
        PlayerDevelopmentService $development,
        array $teamCompetition,
        Carbon $reference,
        array &$minWageCache,
    ): array {
        $tmId = (string) $row->transfermarkt_id;
        $newOverall = (int) $json['overall_score'];
        $updates = [];

        // --- Date of birth -------------------------------------------------
        $dob = null;
        if (!empty($row->date_of_birth)) {
            try {
                $dob = Carbon::parse($row->date_of_birth);
            } catch (\Exception $e) {
                // keep null
            }
        }
        $isPlaceholderDob = !$dob || $dob->toDateString() === '2000-01-01';
        if (!empty($json['dateOfBirth'])) {
            // Real DOB curated in the JSON: adopt it if the row has none/placeholder.
            if ($isPlaceholderDob) {
                try {
                    $dob = Carbon::parse($json['dateOfBirth']);
                    $updates['date_of_birth'] = $dob->toDateString();
                } catch (\Exception $e) {
                    // keep placeholder handling below
                }
            }
        }
        if ($isPlaceholderDob && !isset($updates['date_of_birth'])) {
            $dob = GamePlayerTemplateService::variedDefaultDob($tmId, self::SEASON);
            $updates['date_of_birth'] = $dob->toDateString();
        }

        $age = $dob ? (int) $dob->diffInYears($reference) : 24;

        // --- Market value: more rating -> more value ------------------------
        $jsonMvCents = Money::parseMarketValue($json['marketValue'] ?? null);
        $mvCents = (int) $row->market_value_cents;
        $mvLabel = $row->market_value;
        if ($jsonMvCents <= 0 && $mvCents === self::FLOOR_CENTS) {
            // No real market value: derive it from the rating instead of the
            // flat EUR 25K floor.
            $mvCents = $valuation->overallScoreToMarketValue($newOverall, $age, null, $row->position);
            $mvLabel = Money::format($mvCents);
        }
        if ($mvCents !== (int) $row->market_value_cents) {
            $updates['market_value_cents'] = $mvCents;
            $updates['market_value'] = $mvLabel;
        }

        // --- Overall --------------------------------------------------------
        if ($newOverall !== (int) $row->overall_score) {
            $updates['overall_score'] = $newOverall;
        }

        // --- Contract: varied default instead of flat 2027-06-30 ------------
        $jsonContract = $json['contract'] ?? null;
        if ((empty($jsonContract) || $jsonContract === '-') && $row->contract_until === '2027-06-30') {
            $updates['contract_until'] = GamePlayerTemplateService::variedDefaultContract($tmId, $reference);
        }

        // --- Wage / potential / tier ----------------------------------------
        if (isset($updates['overall_score']) || isset($updates['market_value_cents']) || isset($updates['date_of_birth'])) {
            if (!array_key_exists($row->team_id, $minWageCache)) {
                $competitionId = $teamCompetition[$row->team_id] ?? null;
                $minWageCache[$row->team_id] = $competitionId
                    ? $contracts->getMinimumWageForCompetition($competitionId, $row->team_id)
                    : 0;
            }

            $updates['annual_wage'] = $contracts->calculateAnnualWageForPlayer(
                $newOverall, $mvCents, $minWageCache[$row->team_id], $age, $row->position, true
            );

            srand(crc32('potential:' . $tmId)); // deterministic per player
            $potential = $development->generatePotential($age, $newOverall, $mvCents);
            srand();

            $updates['potential'] = $potential['potential'];
            $updates['potential_low'] = $potential['low'];
            $updates['potential_high'] = $potential['high'];
            $updates['tier'] = PlayerTierService::tierFromMarketValue($mvCents);
        }

        return $updates;
    }

    /**
     * Apply [id => [col => value]] updates with bulk CASE-based UPDATE
     * statements (400 rows per statement) to avoid one roundtrip per row.
     */
    private function bulkUpdate(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 400, true) as $chunk) {
            $ids = array_keys($chunk);
            $cols = [];
            foreach ($chunk as $u) {
                foreach (array_keys($u) as $c) {
                    $cols[$c] = true;
                }
            }

            $setParts = [];
            $bindings = [];
            foreach (array_keys($cols) as $col) {
                $case = "\"{$col}\" = CASE \"id\"";
                foreach ($chunk as $id => $u) {
                    if (array_key_exists($col, $u)) {
                        $case .= ' WHEN ? THEN ?';
                        $bindings[] = $id;
                        $bindings[] = $u[$col];
                    }
                }
                $case .= " ELSE \"{$col}\" END";
                $setParts[] = $case;
            }

            foreach ($ids as $id) {
                $bindings[] = $id;
            }
            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

            DB::update(
                'UPDATE "game_player_templates" SET ' . implode(', ', $setParts)
                    . " WHERE \"id\" IN ({$inPlaceholders})",
                $bindings
            );
        }
    }
};

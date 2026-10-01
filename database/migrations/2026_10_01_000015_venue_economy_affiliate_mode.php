<?php

use App\Models\Game;
use App\Models\Team;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Player\Services\PlayerValuationService;
use App\Modules\Transfer\Services\ContractService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SEASON = '2026';
    private const FLOOR_CENTS = 2_500_000; // €25K

    /**
     * Federation venue budgets (euros) by nation tier, for national-team games.
     * Note: games.federation_budget already exists (migration 000012, euros).
     */
    private const BUDGET_TOP = 15_000_000;   // €15M
    private const BUDGET_MID = 8_000_000;    // €8M
    private const BUDGET_SMALL = 3_000_000;  // €3M

    private const TOP_NATIONS = [
        'Spain', 'United States', 'England', 'Germany', 'France', 'Brazil',
        'Japan', 'Netherlands', 'Sweden', 'Canada', 'Australia', 'Norway',
        'Denmark', 'Italy', 'Iceland', 'South Korea',
    ];

    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // 'dual' (club+nation) or 'affiliate' (first team + reserve team)
            $table->string('pair_mode', 20)->default('dual');
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->bigInteger('venue_fee')->nullable();
        });

        $this->backfillFederationBudgets();
        $this->applyDivisionBandsToExistingGames();
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('venue_fee');
        });
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('pair_mode');
        });
    }

    /**
     * Existing national-team games get a venue budget by nation tier
     * (only where still at/below the old €2M default).
     */
    private function backfillFederationBudgets(): void
    {
        $games = DB::table('games')
            ->where('game_mode', Game::MODE_TOURNAMENT)
            ->whereNull('deleting_at')
            ->where('federation_budget', '<=', 2_000_000)
            ->select('id', 'team_id')
            ->get();

        foreach ($games as $g) {
            $team = Team::find($g->team_id);
            DB::table('games')->where('id', $g->id)
                ->update(['federation_budget' => self::budgetForNation($team)]);
        }
    }

    public static function budgetForNation(?Team $team): int
    {
        if ($team && in_array($team->name, self::TOP_NATIONS, true)) {
            return self::BUDGET_TOP;
        }
        if ($team && $team->confederation === 'UEFA') {
            return self::BUDGET_MID;
        }
        return self::BUDGET_SMALL;
    }

    /**
     * Apply the division rating bands (Liga F >= 72, 1RFEF >= 60,
     * 2RFEF 40-59, small NT nations without club 40-52) to already
     * materialised templates and game players.
     */
    private function applyDivisionBandsToExistingGames(): void
    {
        $map = $this->loadJsonOverallMap();
        if (empty($map)) {
            return;
        }

        $valuation = app(PlayerValuationService::class);
        $contracts = app(ContractService::class);
        $development = app(PlayerDevelopmentService::class);
        $reference = Carbon::parse(self::SEASON . '-08-15');

        $teamCompetition = DB::table('competition_teams')
            ->where('season', self::SEASON)
            ->pluck('competition_id', 'team_id')
            ->all();
        $minWageCache = [];

        // 1. Templates (shared, season 2026).
        DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->whereIn('transfermarkt_id', array_keys($map))
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($map, $valuation, $contracts, $development, $reference, $teamCompetition, &$minWageCache) {
                $updates = [];
                foreach ($rows as $row) {
                    $newOverall = $map[(string) $row->transfermarkt_id] ?? null;
                    if ($newOverall === null || (int) $row->overall_score === $newOverall) {
                        continue;
                    }
                    $u = $this->computeUpdates($row, $newOverall, $valuation, $contracts, $development, $reference, $teamCompetition, $minWageCache);
                    if (!empty($u)) {
                        $updates[$row->id] = $u;
                    }
                }
                $this->bulkUpdate('game_player_templates', $updates);
            });

        // 2. Per-game players (all live games).
        $gameIds = DB::table('games')->whereNull('deleting_at')->pluck('id')->all();
        foreach (array_chunk($gameIds, 50) as $chunk) {
            DB::table('game_players')
                ->whereIn('game_id', $chunk)
                ->whereIn('transfermarkt_id', array_keys($map))
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($map, $valuation, $contracts, $development, $reference, $teamCompetition, &$minWageCache) {
                    $updates = [];
                    foreach ($rows as $row) {
                        $newOverall = $map[(string) $row->transfermarkt_id] ?? null;
                        if ($newOverall === null || (int) $row->overall_score === $newOverall) {
                            continue;
                        }
                        $u = $this->computeUpdates($row, $newOverall, $valuation, $contracts, $development, $reference, $teamCompetition, $minWageCache);
                        if (!empty($u)) {
                            $updates[$row->id] = $u;
                        }
                    }
                    $this->bulkUpdate('game_players', $updates);
                });
        }
    }

    /**
     * transfermarkt_id => overall_score from the banded data/2026 JSONs.
     * First file wins (same convention as 000003).
     */
    private function loadJsonOverallMap(): array
    {
        $map = [];
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
            foreach ($data['clubs'] ?? $data['teams'] ?? [] as $club) {
                foreach ($club['players'] ?? [] as $p) {
                    $tmId = (string) ($p['id'] ?? '');
                    if ($tmId === '' || isset($map[$tmId]) || empty($p['overall_score'])) {
                        continue;
                    }
                    $map[$tmId] = (int) $p['overall_score'];
                }
            }
            unset($data);
            gc_collect_cycles();
        }

        return $map;
    }

    private function computeUpdates(
        object $row,
        int $newOverall,
        PlayerValuationService $valuation,
        ContractService $contracts,
        PlayerDevelopmentService $development,
        Carbon $reference,
        array $teamCompetition,
        array &$minWageCache,
    ): array {
        $tmId = (string) $row->transfermarkt_id;
        $updates = ['overall_score' => $newOverall];

        $dob = null;
        if (!empty($row->date_of_birth)) {
            try {
                $dob = Carbon::parse($row->date_of_birth);
            } catch (\Exception $e) {
            }
        }
        $age = $dob ? (int) $dob->diffInYears($reference) : 24;

        // Market value: only re-derive when the row sits on the €25K floor
        // (no real quoted value), mirroring 000003.
        $jsonMvCents = 0;
        $mvCents = (int) ($row->market_value_cents ?? 0);
        if ($mvCents === self::FLOOR_CENTS || $mvCents <= 0) {
            $mvCents = $valuation->overallScoreToMarketValue($newOverall, $age, null, $row->position ?? null);
            $updates['market_value_cents'] = $mvCents;
            $updates['market_value'] = Money::format($mvCents);
        }

        if (!array_key_exists($row->team_id ?? '', $minWageCache)) {
            $competitionId = $teamCompetition[$row->team_id] ?? null;
            $minWageCache[$row->team_id] = $competitionId
                ? $contracts->getMinimumWageForCompetition($competitionId, $row->team_id)
                : 0;
        }

        $updates['annual_wage'] = $contracts->calculateAnnualWageForPlayer(
            $newOverall, $mvCents, $minWageCache[$row->team_id] ?? 0, $age, $row->position ?? null, true
        );

        srand(crc32('potential:' . $tmId));
        $potential = $development->generatePotential($age, $newOverall, $mvCents);
        srand();

        $updates['potential'] = $potential['potential'];
        $updates['potential_low'] = $potential['low'];
        $updates['potential_high'] = $potential['high'];
        $updates['tier'] = PlayerTierService::tierFromMarketValue($mvCents);

        return $updates;
    }

    private function bulkUpdate(string $table, array $rows): void
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
                "UPDATE \"{$table}\" SET " . implode(', ', $setParts)
                    . " WHERE \"id\" IN ({$inPlaceholders})",
                $bindings
            );
        }
    }
};

<?php

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
 * Realistic player ratings (FIFA-style), 01-10-2026.
 *
 * Applies the curated ratings from data/2026/asterisk/teams.json +
 * data/players/NAT.json (FC 26 official top-26 women + Liga F top-30 +
 * heuristic tier/age curve for the rest) to the seeded 2026 templates:
 *
 * - overall_score from the JSON rating (explicit FIFA where available)
 * - market_value derived from the rating where the JSON had no real value
 *   (more rating -> more value), instead of the flat EUR 25K floor
 * - annual_wage / potential / tier recalculated from the new numbers
 * - varied placeholder DOBs (no more flat 2000-01-01) and varied default
 *   contracts (no more flat 2027-06-30) for players without real data
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
        $players = $this->loadJsonPlayers();
        if (empty($players)) {
            return;
        }

        $valuation = app(PlayerValuationService::class);
        $contracts = app(ContractService::class);
        $development = app(PlayerDevelopmentService::class);

        // team_id -> competition_id (for the wage minimum)
        $teamCompetition = DB::table('competition_teams')
            ->where('season', self::SEASON)
            ->pluck('competition_id', 'team_id')
            ->all();

        $reference = Carbon::parse(self::SEASON . '-08-15');
        $me = $this;

        DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($me, $players, $valuation, $contracts, $development, $teamCompetition, $reference) {
                foreach ($rows as $row) {
                    $me->refreshRow($row, $players, $valuation, $contracts, $development, $teamCompetition, $reference);
                }
            });
    }

    public function down(): void
    {
        // Data migration: ratings cannot be rolled back automatically.
    }

    /**
     * All JSON players keyed by transfermarkt_id (string).
     */
    private function loadJsonPlayers(): array
    {
        $players = [];
        $files = glob(base_path('data/2026/*/teams.json')) ?: [];
        $natFile = base_path('data/2026/NAT.json');
        if (is_file($natFile)) {
            $files[] = $natFile;
        }

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $clubs = $data['clubs'] ?? $data['teams'] ?? [];
            foreach ($clubs as $club) {
                foreach ($club['players'] ?? [] as $p) {
                    $tmId = (string) ($p['id'] ?? '');
                    if ($tmId === '' || isset($players[$tmId])) {
                        continue;
                    }
                    $players[$tmId] = [
                        'overall_score' => $p['overall_score'] ?? null,
                        'marketValue' => $p['marketValue'] ?? null,
                        'dateOfBirth' => $p['dateOfBirth'] ?? null,
                        'contract' => $p['contract'] ?? null,
                    ];
                }
            }
        }

        return $players;
    }

    private function refreshRow(
        object $row,
        array $players,
        PlayerValuationService $valuation,
        ContractService $contracts,
        PlayerDevelopmentService $development,
        array $teamCompetition,
        Carbon $reference,
    ): bool {
        $tmId = (string) $row->transfermarkt_id;
        $json = $players[$tmId] ?? null;
        if (!$json || empty($json['overall_score'])) {
            return false;
        }

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
            $competitionId = $teamCompetition[$row->team_id] ?? null;
            $minimumWage = $competitionId
                ? $contracts->getMinimumWageForCompetition($competitionId, $row->team_id)
                : 0;

            $updates['annual_wage'] = $contracts->calculateAnnualWageForPlayer(
                $newOverall, $mvCents, $minimumWage, $age, $row->position, true
            );

            srand(crc32('potential:' . $tmId)); // deterministic per player
            $potential = $development->generatePotential($age, $newOverall, $mvCents);
            srand();

            $updates['potential'] = $potential['potential'];
            $updates['potential_low'] = $potential['low'];
            $updates['potential_high'] = $potential['high'];
            $updates['tier'] = PlayerTierService::tierFromMarketValue($mvCents);
        }

        if (empty($updates)) {
            return false;
        }

        DB::table('game_player_templates')->where('id', $row->id)->update($updates);

        return true;
    }
};

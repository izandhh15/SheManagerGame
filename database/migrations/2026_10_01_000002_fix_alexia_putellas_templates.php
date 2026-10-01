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
 * Data fix: Alexia Putellas was seeded under London City Lionesses (ENG1)
 * with a placeholder id and never existed for FC Barcelona, so she was
 * missing from the player search and from Spain's call-up pool.
 *
 * - Deletes the misfiled template row(s).
 * - Inserts her proper 2026 templates: FC Barcelona (club) and Spain
 *   (national-team backfill equivalent), reusing the same valuation /
 *   wage / potential services the seeders use so ratings stay consistent.
 *
 * Idempotent: re-running changes nothing (rows are deleted + reinserted
 * by transfermarkt_id).
 */
return new class extends Migration
{
    private const SEASON = '2026';

    private const TM_ID = '904001';

    public function up(): void
    {
        // 1. Remove the misfiled bogus row(s).
        DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->where('transfermarkt_id', '900570')
            ->delete();

        $barcaId = DB::table('teams')
            ->where('type', '!=', 'national')
            ->where('name', 'FC Barcelona')
            ->value('id');
        $spainId = DB::table('teams')
            ->where('type', 'national')
            ->where('fifa_code', 'ESP')
            ->value('id');

        if (!$barcaId && !$spainId) {
            return;
        }

        // 2. Build the template rows with the same formulas the seeders use.
        $playerId = GamePlayerTemplateService::playerIdFor(self::TM_ID);

        $dob = Carbon::parse('1994-02-04');
        $age = (int) $dob->diffInYears(Carbon::parse(self::SEASON . '-08-15'));
        $marketValueCents = Money::parseMarketValue('€500k');

        $valuation = app(PlayerValuationService::class);
        $contracts = app(ContractService::class);
        $development = app(PlayerDevelopmentService::class);

        $overall = $valuation->marketValueToOverallScore($marketValueCents, $age, 'Midfielder');
        $potential = $development->generatePotential($age, $overall);

        $base = [
            'season' => self::SEASON,
            'player_id' => $playerId,
            'transfermarkt_id' => self::TM_ID,
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
        ];

        $rows = [];

        if ($barcaId) {
            $rows[] = $base + [
                'team_id' => $barcaId,
                'number' => 11,
                'annual_wage' => $contracts->calculateAnnualWageForPlayer($overall, $marketValueCents, 0, $age, 'Midfielder'),
                'release_clause' => $contracts->calculateReleaseClause($marketValueCents, 'ES'),
            ];
        }

        if ($spainId) {
            // NT templates carry no squad number (partial unique index on
            // (season, team_id, number)); the picker is the call-up pool.
            $rows[] = $base + [
                'team_id' => $spainId,
                'number' => null,
                'annual_wage' => $contracts->calculateAnnualWageForPlayer($overall, $marketValueCents, 0, $age, 'Midfielder'),
                'release_clause' => $contracts->calculateReleaseClause($marketValueCents, null),
            ];
        }

        foreach ($rows as $row) {
            DB::table('game_player_templates')
                ->where('season', self::SEASON)
                ->where('team_id', $row['team_id'])
                ->where('transfermarkt_id', self::TM_ID)
                ->delete();
            DB::table('game_player_templates')->insert($row);
        }
    }

    public function down(): void
    {
        DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->where('transfermarkt_id', self::TM_ID)
            ->delete();
    }
};

<?php

namespace App\Console\Commands;

use App\Modules\Season\Services\GamePlayerTemplateService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Support\CountryCodeMapper;
use App\Support\TeamColors;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Seed all women's national teams (beta).
 *
 * Reads data/2026/NAT.json (198 national teams, ~2.100 players) and:
 *  - inserts the national teams into `teams` (type='national'), persisting
 *    each team's FIFA confederation (nullable — tolerated when missing)
 *  - generates game_player_templates via GamePlayerTemplateService
 *  - registers the 6 Women's World Cup qualifying competitions, one per
 *    confederation (WQUEFA, WQAFC, WQCAF, WQCONC, WQCONM, WQOFC)
 *  - links each NT to its confederation's competition in competition_teams
 *
 * Legacy: the original single competition 'WWCQ' row is kept (beta saves
 * reference it as competition_id) but no longer receives team links; the
 * runtime still accepts it as an alias with the global draw. The
 * confederation → competition mapping lives in TournamentCreationService
 * (single source of truth, also used at game creation).
 *
 * National-team templates are excluded from career-mode setup; they only
 * materialise in national-team games. Players without a club template
 * become free agents in career mode (SetupNewGame handles that).
 */
class SeedNationalTeams extends Command
{
    protected $signature = 'app:seed-national-teams
                            {--fresh : Clear existing national-team data before seeding}';

    protected $description = "Seed women's national teams (NAT.json) and their player templates (beta)";

    /**
     * @deprecated Kept as the legacy alias id (beta saves use it as
     * competition_id). New code should resolve ids through
     * TournamentCreationService instead.
     */
    public const COMPETITION_ID = 'WWCQ';
    public const SEASON = '2026';

    /**
     * confederation => [competition_id, display name].
     * The competition ids must match
     * TournamentCreationService::competitionIdForConfederation() — verified
     * row by row in seedCompetition().
     */
    private const QUALIFIER_COMPETITIONS = [
        'UEFA'     => ['WQUEFA', 'Clasificación Mundial 2027 · UEFA'],
        'AFC'      => ['WQAFC', 'Clasificación Mundial 2027 · AFC'],
        'CAF'      => ['WQCAF', 'Clasificación Mundial 2027 · CAF'],
        'CONCACAF' => ['WQCONC', 'Clasificación Mundial 2027 · CONCACAF'],
        'CONMEBOL' => ['WQCONM', 'Clasificación Mundial 2027 · CONMEBOL'],
        'OFC'      => ['WQOFC', 'Clasificación Mundial 2027 · OFC'],
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $path = base_path('data/' . self::SEASON . '/NAT.json');
        if (!file_exists($path)) {
            $this->error("NAT.json not found at {$path}");
            return CommandAlias::FAILURE;
        }

        $clubs = json_decode(file_get_contents($path), true)['clubs'] ?? [];
        $this->info('Seeding ' . count($clubs) . ' national teams...');

        $this->seedCompetition();
        $this->seedTeams($clubs);

        $count = app(GamePlayerTemplateService::class)->generateForNationalTeams(self::SEASON);
        $this->info("Generated {$count} player templates for national teams.");

        return CommandAlias::SUCCESS;
    }

    /**
     * @param array<int, array{name: string, fifa_code: string, type: string, confederation?: string}> $clubs
     */
    private function seedTeams(array $clubs): void
    {
        // Rebuild the qualifier links from scratch: teams migrate from the
        // legacy global WWCQ link to their confederation's competition, so
        // stale rows for the qualifier ids are dropped first. These rows are
        // pure seed data — games keep their own competition_entries — so this
        // is safe for existing saves.
        DB::table('competition_teams')
            ->whereIn('competition_id', TournamentCreationService::WQC_IDS)
            ->where('season', self::SEASON)
            ->delete();

        $inserted = 0;
        $updated = 0;

        foreach ($clubs as $club) {
            $name = $club['name'];
            $fifaCode = $club['fifa_code'] ?? null;
            $countryCode = CountryCodeMapper::toCode($name);

            if (!$countryCode) {
                $this->warn("  No country code for '{$name}' — skipped");
                continue;
            }

            // Tolerate missing/unknown confederations: those teams fall back
            // to the legacy WWCQ competition (and its global draw).
            $confederation = $club['confederation'] ?? null;
            if ($confederation !== null && !array_key_exists($confederation, self::QUALIFIER_COMPETITIONS)) {
                $this->warn("  Unknown confederation '{$confederation}' for '{$name}' — linked to legacy WWCQ");
                $confederation = null;
            }

            $existing = DB::table('teams')
                ->where('type', 'national')
                ->where('fifa_code', $fifaCode)
                ->first();

            if ($existing) {
                DB::table('teams')->where('id', $existing->id)->update([
                    'name' => $name,
                    'country' => $countryCode,
                    'confederation' => $confederation,
                    'colors' => $existing->colors ?? json_encode(TeamColors::get($name)),
                ]);
                $teamId = $existing->id;
                $updated++;
            } else {
                $teamId = Str::uuid()->toString();
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => null,
                    'type' => 'national',
                    'fifa_code' => $fifaCode,
                    'is_placeholder' => false,
                    'name' => $name,
                    'country' => $countryCode,
                    'confederation' => $confederation,
                    'image' => null,
                    'stadium_name' => null,
                    'stadium_seats' => 0,
                    'colors' => json_encode(TeamColors::get($name)),
                ]);
                $inserted++;
            }

            // Each NT is linked to its confederation's qualifier (null →
            // legacy WWCQ alias, which deliberately receives no other links).
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => TournamentCreationService::competitionIdForConfederation($confederation),
                    'team_id' => $teamId,
                    'season' => self::SEASON,
                ],
                []
            );
        }

        $this->info("  Teams: {$inserted} inserted, {$updated} updated.");
    }

    private function seedCompetition(): void
    {
        // Legacy alias row: beta saves have competition_id='WWCQ'. It is
        // deliberately NOT deleted and receives no team links anymore; the
        // setup job still handles it with the global draw.
        DB::table('competitions')->updateOrInsert(
            ['id' => self::COMPETITION_ID],
            [
                'name' => "Women's World Cup Qualifiers",
                'country' => 'IN',
                'flag' => null,
                'tier' => 1,
                'type' => 'league',
                'role' => 'league',
                'scope' => 'continental',
                'handler_type' => 'league',
                'season' => self::SEASON,
            ]
        );

        foreach (self::QUALIFIER_COMPETITIONS as $confederation => [$id, $name]) {
            // The seeder's table and the runtime mapping must agree — a drift
            // here would seed competitions no game could ever reference.
            $expected = TournamentCreationService::competitionIdForConfederation($confederation);
            if ($expected !== $id) {
                $this->error("  Mapping mismatch: {$confederation} => seeder {$id} vs service {$expected} — skipped");
                continue;
            }

            DB::table('competitions')->updateOrInsert(
                ['id' => $id],
                [
                    'name' => $name,
                    'country' => 'IN',
                    'flag' => null,
                    'tier' => 1,
                    'type' => 'league',
                    'role' => 'league',
                    'scope' => 'continental',
                    'handler_type' => 'league',
                    'season' => self::SEASON,
                ]
            );

            $this->info("  Competition: {$id} ({$name}).");
        }
    }

    private function clearExistingData(): void
    {
        $this->warn('Clearing existing national-team data...');

        $teamIds = DB::table('teams')->where('type', 'national')->pluck('id');

        DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->whereIn('team_id', $teamIds)
            ->delete();

        // The 6 confederation competitions plus the legacy WWCQ alias.
        DB::table('competition_teams')
            ->whereIn('competition_id', TournamentCreationService::WQC_IDS)
            ->delete();

        DB::table('competitions')->whereIn('id', TournamentCreationService::WQC_IDS)->delete();

        // Keep WC2026 teams (seeded separately); only remove teams that came
        // from NAT.json — i.e. national teams not in WC2026's 48.
        $wcTeamIds = DB::table('competition_teams')
            ->where('competition_id', 'WC2026')
            ->pluck('team_id');

        DB::table('teams')
            ->where('type', 'national')
            ->whereNotIn('id', $wcTeamIds)
            ->delete();

        $this->info('  Cleared.');
    }
}

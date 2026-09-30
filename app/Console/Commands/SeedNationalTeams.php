<?php

namespace App\Console\Commands;

use App\Modules\Season\Services\GamePlayerTemplateService;
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
 *  - inserts the national teams into `teams` (type='national')
 *  - generates game_player_templates via GamePlayerTemplateService
 *  - registers the WWCQ competition (Women's World Cup Qualifiers)
 *  - links all NTs to WWCQ in competition_teams
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

    public const COMPETITION_ID = 'WWCQ';
    public const SEASON = '2026';

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
     * @param array<int, array{name: string, fifa_code: string, type: string}> $clubs
     */
    private function seedTeams(array $clubs): void
    {
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

            $existing = DB::table('teams')
                ->where('type', 'national')
                ->where('fifa_code', $fifaCode)
                ->first();

            if ($existing) {
                DB::table('teams')->where('id', $existing->id)->update([
                    'name' => $name,
                    'country' => $countryCode,
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
                    'image' => null,
                    'stadium_name' => null,
                    'stadium_seats' => 0,
                    'colors' => json_encode(TeamColors::get($name)),
                ]);
                $inserted++;
            }

            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => self::COMPETITION_ID,
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

        $this->info('  Competition: WWCQ (Women\'s World Cup Qualifiers).');
    }

    private function clearExistingData(): void
    {
        $this->warn('Clearing existing national-team data...');

        $teamIds = DB::table('teams')->where('type', 'national')->pluck('id');

        DB::table('game_player_templates')
            ->where('season', self::SEASON)
            ->whereIn('team_id', $teamIds)
            ->delete();

        DB::table('competition_teams')
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        DB::table('competitions')->where('id', self::COMPETITION_ID)->delete();

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

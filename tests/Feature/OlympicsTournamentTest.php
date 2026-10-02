<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Competition\Configs\WomensOlympicsConfig;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;
use App\Modules\Season\Services\TournamentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Women's Olympic football tournament (WOLYMP, Los Angeles 2028):
 * the competition row, its config, its place in the national-team
 * calendar and its 12-team format (3 groups of 4, top 2 + 2 best
 * thirds through to the quarter-finals).
 */
class OlympicsTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_olympics_competition_is_seeded_by_migration(): void
    {
        $competition = Competition::find('WOLYMP');

        $this->assertNotNull($competition);
        $this->assertSame('Juegos Olímpicos 2028', $competition->name);
        $this->assertSame('group_stage_cup', $competition->handler_type);
        $this->assertSame('Juegos Olímpicos', $competition->shortName());

        // "los Juegos Olímpicos" — masculine plural article.
        $this->assertSame('los Juegos Olímpicos', $competition->nameWithEl());
        $this->assertSame('de los Juegos Olímpicos', $competition->nameWithDe());
        $this->assertSame('a los Juegos Olímpicos', $competition->nameWithA());
        $this->assertSame('en los Juegos Olímpicos', $competition->nameWithEn());
    }

    public function test_olympics_config_resolves_and_pays_less_than_the_world_cup(): void
    {
        $class = app(CountryConfig::class)->configClassForCompetition('WOLYMP');
        $this->assertSame(WomensOlympicsConfig::class, $class);

        $config = new WomensOlympicsConfig();
        $this->assertSame('Juegos Olímpicos 2028', $config->getName());
        $this->assertSame(1000_000_000, $config->getTvRevenue(1)); // €10M champion

        // The standings mark the top 2 (green) and the third-place
        // "best thirds can still go through" spot (amber).
        $zones = $config->getStandingsZones();
        $this->assertCount(2, $zones);
        $this->assertSame(1, $zones[0]['minPosition']);
        $this->assertSame(2, $zones[0]['maxPosition']);
        $this->assertSame(3, $zones[1]['minPosition']);
        $this->assertSame(3, $zones[1]['maxPosition']);

        // The new lang key exists (ES + fallback EN).
        $this->assertNotSame('game.knockout_best_third', __('game.knockout_best_third'));
    }

    public function test_olympics_sits_in_the_national_calendar_after_the_world_cup(): void
    {
        $this->assertContains('WOLYMP', TournamentCreationService::NATIONAL_TEAM_COMPETITION_IDS);
        $this->assertContains('WOLYMP', TournamentCreationService::NATIONAL_TEAM_FINAL_IDS);

        // World Cup 2027 → Olympics 2028 (defend the gold!).
        $this->assertSame(
            TournamentCreationService::WOLYMP_ID,
            TournamentCreationService::nextCompetitionInSequence(TournamentCreationService::WWCU27_ID)
        );

        // After the Olympics the team returns to its confederation's
        // competition (Nations League for UEFA sides like Spain).
        $spain = Team::factory()->create([
            'type' => 'national',
            'name' => 'Spain',
            'fifa_code' => 'ESP',
            'confederation' => 'UEFA',
        ]);
        $game = Game::factory()->forTeam($spain)->inCompetition('WOLYMP')->create();

        $this->assertSame(
            'WNL',
            TournamentCreationService::nextCompetitionInSequence('WOLYMP', $game)
        );
    }

    public function test_olympics_bracket_and_schedule_files_are_valid(): void
    {
        $bracket = json_decode(
            file_get_contents(base_path('data/2026/WOLYMP/bracket.json')),
            true
        );

        $this->assertCount(4, $bracket['quarter_finals']);

        // Every QF slot is unique and the two best-third slots are there.
        $slots = [];
        foreach ($bracket['quarter_finals'] as $match) {
            $slots[] = $match['home'];
            $slots[] = $match['away'];
        }
        $this->assertCount(8, array_unique($slots));
        $this->assertContains('3RD1', $slots);
        $this->assertContains('3RD2', $slots);

        $this->assertCount(2, $bracket['semi_finals']);
        $this->assertNotEmpty($bracket['third_place']); // bronze match, like the real thing
        $this->assertNotEmpty($bracket['final']);

        $schedule = json_decode(
            file_get_contents(base_path('data/2026/WOLYMP/schedule.json')),
            true
        );
        $this->assertCount(3, $schedule['league']);
        $this->assertNotEmpty($schedule['knockout']);

        $generator = app(WorldCupKnockoutGenerator::class);
        $this->assertSame(
            WorldCupKnockoutGenerator::ROUND_QUARTER_FINALS,
            $generator->getFirstKnockoutRound(8)
        );
        $this->assertTrue($generator->hasThirdPlaceMatch('WOLYMP'));
    }

    public function test_olympics_advances_top_two_plus_two_best_thirds(): void
    {
        $game = Game::factory()->create(['competition_id' => 'WOLYMP']);

        // 3 groups of 4. Third-place points: B (7) > A (4) > C (1), so the
        // two best thirds are B3 and A3.
        $groups = [
            'A' => ['A1', 'A2', 'A3', 'A4'],
            'B' => ['B1', 'B2', 'B3', 'B4'],
            'C' => ['C1', 'C2', 'C3', 'C4'],
        ];
        $thirdPlacePoints = ['A' => 4, 'B' => 7, 'C' => 1];

        $teamIds = [];
        foreach ($groups as $label => $codes) {
            foreach ($codes as $position => $code) {
                $team = Team::factory()->create([
                    'type' => 'national',
                    'name' => "Olympic {$code}",
                    'fifa_code' => $code,
                ]);
                $points = $position < 2 ? 9 - $position : ($thirdPlacePoints[$label] ?? 0);
                GameStanding::create([
                    'game_id' => $game->id,
                    'competition_id' => 'WOLYMP',
                    'group_label' => $label,
                    'team_id' => $team->id,
                    'position' => $position + 1,
                    'played' => 3,
                    'points' => $points,
                    'goals_for' => 5,
                    'goals_against' => 2,
                ]);
                $teamIds[$code] = $team->id;
            }
        }

        $qualified = app(WorldCupKnockoutGenerator::class)
            ->getQualifiedTeams($game->id, 'WOLYMP');

        // 6 group top-two + 2 best thirds = 8 quarter-finalists.
        $this->assertCount(8, $qualified);
        foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'B3', 'A3'] as $code) {
            $this->assertContains($teamIds[$code], $qualified, "expected {$code} to qualify");
        }
        $this->assertNotContains($teamIds['C3'], $qualified); // worst third stays home
        $this->assertNotContains($teamIds['A4'], $qualified);

        // The ranked thirds list powers the 3RD1/3RD2 bracket slots.
        $rankedThirds = app(WorldCupKnockoutGenerator::class)
            ->getRankedThirdPlaceTeams($game->id, 'WOLYMP');
        $this->assertSame([$teamIds['B3'], $teamIds['A3'], $teamIds['C3']], $rankedThirds->values()->all());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ClubWorldCupInitProcessor;
use App\Modules\Season\Processors\ClubWorldCupQualificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Women's Club World Cup (CWC):
 * - Qualification at season close picks the best clubs per confederation
 *   by squad market value (16 UEFA, 8 CONMEBOL, 8 CONCACAF).
 * - Season setup draws 8 groups of 4 honouring the confederation limits
 *   and generates the group-stage fixtures + standings.
 */
class ClubWorldCupTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::updateOrCreate(['id' => 'CWC'], Competition::factory()->groupStageCup()->make([
            'id' => 'CWC',
            'name' => 'Mundial de Clubes',
            'country' => 'XX',
            'season' => '2026',
        ])->toArray());

        $this->userTeam = Team::factory()->create(['country' => 'ES', 'type' => 'club']);

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'game_mode' => Game::MODE_CAREER,
            'country' => 'ES',
            'season' => '2026',
            'base_season' => '2026',
        ]);
    }

    /**
     * Create a club with an 11-player squad worth $valueMillions € in total.
     */
    private function makeClub(string $country, int $valueMillions): Team
    {
        $team = Team::factory()->create(['country' => $country, 'type' => 'club']);

        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->count(11)
            ->create(['market_value_cents' => (int) ($valueMillions * 100_000_000 / 11)]);

        return $team;
    }

    private function transitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );
    }

    private function qualifiedIds(): array
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'CWC')
            ->pluck('team_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function test_qualifies_best_clubs_per_confederation_by_squad_value(): void
    {
        // 17 UEFA clubs (user's is the strongest at €30M, rest €1M-€16M)
        $uefaClubs = [];
        for ($i = 1; $i <= 16; $i++) {
            $uefaClubs[$i] = $this->makeClub('ES', $i);
        }
        GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->count(11)
            ->create(['market_value_cents' => (int) (30 * 100_000_000 / 11)]);

        // 8 CONMEBOL + 8 CONCACAF clubs, €1M-€8M each
        for ($i = 1; $i <= 8; $i++) {
            $this->makeClub($i % 2 ? 'AR' : 'BR', $i);
            $this->makeClub($i % 2 ? 'MX' : 'US', $i);
        }

        app(ClubWorldCupQualificationProcessor::class)->process($this->game, $this->transitionData());

        $qualified = $this->qualifiedIds();

        // 16 UEFA + 8 CONMEBOL + 8 CONCACAF = 32
        $this->assertCount(32, $qualified);

        // The user's €30M club qualifies…
        $this->assertContains((string) $this->userTeam->id, $qualified);

        // …while the weakest UEFA club (€1M, 17th UEFA side) misses out.
        $this->assertNotContains((string) $uefaClubs[1]->id, $qualified);
    }

    public function test_user_gets_notified_when_their_club_qualifies(): void
    {
        GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->count(11)
            ->create(['market_value_cents' => 100_000_000]);

        app(ClubWorldCupQualificationProcessor::class)->process($this->game, $this->transitionData());

        $this->assertTrue(
            GameNotification::where('game_id', $this->game->id)
                ->where('type', 'cwc_qualified')
                ->exists()
        );
    }

    public function test_reserve_and_national_teams_never_qualify(): void
    {
        $reserve = Team::factory()->create([
            'country' => 'ES',
            'type' => 'club',
            'parent_team_id' => $this->userTeam->id,
        ]);
        GamePlayer::factory()->forGame($this->game)->forTeam($reserve)->count(11)
            ->create(['market_value_cents' => 500_000_000]);

        $national = Team::factory()->create(['country' => 'ES', 'type' => 'national']);
        GamePlayer::factory()->forGame($this->game)->forTeam($national)->count(11)
            ->create(['market_value_cents' => 500_000_000]);

        app(ClubWorldCupQualificationProcessor::class)->process($this->game, $this->transitionData());

        $qualified = $this->qualifiedIds();

        $this->assertNotContains((string) $reserve->id, $qualified);
        $this->assertNotContains((string) $national->id, $qualified);
    }

    public function test_skips_non_career_games(): void
    {
        $this->game->update(['game_mode' => Game::MODE_TOURNAMENT]);

        app(ClubWorldCupQualificationProcessor::class)->process($this->game, $this->transitionData());

        $this->assertCount(0, $this->qualifiedIds());
    }

    public function test_draws_eight_groups_with_confederation_limits(): void
    {
        // 16 UEFA + 8 CONMEBOL + 8 CONCACAF, staggered values
        for ($i = 1; $i <= 15; $i++) {
            $this->makeClub('ES', $i);
        }
        GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->count(11)
            ->create(['market_value_cents' => (int) (30 * 100_000_000 / 11)]);
        for ($i = 1; $i <= 8; $i++) {
            $this->makeClub($i % 2 ? 'AR' : 'BR', $i);
            $this->makeClub($i % 2 ? 'MX' : 'US', $i);
        }

        app(ClubWorldCupQualificationProcessor::class)->process($this->game, $this->transitionData());
        app(ClubWorldCupInitProcessor::class)->process($this->game, $this->transitionData());

        // 8 groups of 4 in the standings, labelled A-H
        $standings = GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'CWC')
            ->get();

        $this->assertCount(32, $standings);
        $this->assertEquals(
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
            $standings->pluck('group_label')->unique()->sort()->values()->all()
        );

        // 48 group matches (8 groups × 6), dated in July 2026
        $matches = GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'CWC')
            ->get();

        $this->assertCount(48, $matches);
        foreach ($matches as $match) {
            $this->assertStringStartsWith('2026-07-', $match->scheduled_date->toDateString());
        }

        // Confederation limits per group: max 2 UEFA, max 1 CONMEBOL/CONCACAF
        $countries = Team::whereIn('id', $standings->pluck('team_id'))->pluck('country', 'id');
        foreach (range('A', 'H') as $label) {
            $groupCountries = $standings->where('group_label', $label)
                ->map(fn ($row) => $countries[$row->team_id]);

            $uefa = $groupCountries->filter(fn ($c) => !in_array($c, ['AR', 'BR', 'MX', 'US'], true))->count();
            $conmebol = $groupCountries->filter(fn ($c) => in_array($c, ['AR', 'BR'], true))->count();
            $concacaf = $groupCountries->filter(fn ($c) => in_array($c, ['MX', 'US'], true))->count();

            $this->assertLessThanOrEqual(2, $uefa, "group {$label} UEFA limit");
            $this->assertLessThanOrEqual(1, $conmebol, "group {$label} CONMEBOL limit");
            $this->assertLessThanOrEqual(1, $concacaf, "group {$label} CONCACAF limit");
            $this->assertCount(4, $groupCountries);
        }
    }

    public function test_init_is_idempotent(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->makeClub('ES', $i);
        }
        GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->count(11)
            ->create(['market_value_cents' => (int) (30 * 100_000_000 / 11)]);
        for ($i = 1; $i <= 8; $i++) {
            $this->makeClub($i % 2 ? 'AR' : 'BR', $i);
            $this->makeClub($i % 2 ? 'MX' : 'US', $i);
        }

        app(ClubWorldCupQualificationProcessor::class)->process($this->game, $this->transitionData());

        $processor = app(ClubWorldCupInitProcessor::class);
        $processor->process($this->game, $this->transitionData());
        $processor->process($this->game, $this->transitionData());

        $this->assertCount(
            48,
            GameMatch::where('game_id', $this->game->id)->where('competition_id', 'CWC')->get()
        );
    }

    public function test_knockout_schedule_and_config_resolve(): void
    {
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds('CWC', '2026', '2026');

        // R16, QF, SF, Final — no third-place match
        $this->assertEquals([2, 3, 4, 6], array_map(fn ($r) => $r->round, $rounds));
        $this->assertSame(6, LeagueFixtureGenerator::finalKnockoutRound('CWC', '2026'));

        $config = Competition::find('CWC')->getConfig();
        $this->assertInstanceOf(
            \App\Modules\Competition\Configs\ClubWorldCupConfig::class,
            $config
        );
        $this->assertGreaterThan(0, $config->getKnockoutPrizeMoney(0)); // champions get paid
        $this->assertSame('Mundial de Clubes', Competition::find('CWC')->shortName());
    }
}

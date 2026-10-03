<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Match\Listeners\UpdateGoalkeeperStats;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 9 de la revisión de medios (grupos 07/08): las stats de portera solo
 * se repartían entre las titulares (la que entraba de cambio no acumulaba
 * nada y la titular cargaba con todos los goles) y se ignoraba la prórroga
 * (home_score_et/away_score_et), llegando a dar clean sheet a quien solo
 * encajaba en la ET. Ahora los goles se prorratean por minutos entre TODAS
 * las porteras que jugaron, incluyendo la prórroga.
 */
class GoalkeeperStatsFixTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Competition $competition;
    private Team $home;
    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->competition = Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $this->home = Team::factory()->create(['country' => 'ES']);
        $this->away = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $this->home->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);
    }

    public function test_goals_are_prorated_by_minutes_between_starter_and_sub(): void
    {
        $starter = $this->keeper($this->home);
        $sub = $this->keeper($this->home);
        $awayKeeper = $this->keeper($this->away);

        // La titular sale en el 60' (2 goles encajados en el 0-2).
        $match = $this->match(
            homeScore: 0,
            awayScore: 2,
            homeLineup: [$starter->id],
            awayLineup: [$awayKeeper->id],
            substitutions: [[
                'team_id' => $this->home->id,
                'player_out_id' => $starter->id,
                'player_in_id' => $sub->id,
                'minute' => 60,
            ]],
        );

        $this->handle($match);

        // 60' vs 30' → 1 y 1 (suman los 2 reales); nadie con clean sheet.
        $this->assertSame(1, $this->stat($starter, 'goals_conceded'));
        $this->assertSame(1, $this->stat($sub, 'goals_conceded'), 'la suplente debe acumular su parte');
        $this->assertSame(0, $this->stat($starter, 'clean_sheets'));
        $this->assertSame(0, $this->stat($sub, 'clean_sheets'));

        // La portera visitante no encajó: clean sheet intacto.
        $this->assertSame(0, $this->stat($awayKeeper, 'goals_conceded'));
        $this->assertSame(1, $this->stat($awayKeeper, 'clean_sheets'));
    }

    public function test_extra_time_goals_count_and_void_the_clean_sheet(): void
    {
        $homeKeeper = $this->keeper($this->home);
        $awayKeeper = $this->keeper($this->away);

        // 0-0 en el reglamento, 0-1 en la prórroga.
        $match = $this->match(
            homeScore: 0,
            awayScore: 0,
            homeLineup: [$homeKeeper->id],
            awayLineup: [$awayKeeper->id],
            extraTime: ['home' => 0, 'away' => 1],
        );

        $this->handle($match);

        // El gol de la prórroga cuenta y anula el clean sheet (antes: 0
        // goles y clean sheet por mirar solo el marcador del reglamento).
        $this->assertSame(1, $this->stat($homeKeeper, 'goals_conceded'));
        $this->assertSame(0, $this->stat($homeKeeper, 'clean_sheets'));

        $this->assertSame(0, $this->stat($awayKeeper, 'goals_conceded'));
        $this->assertSame(1, $this->stat($awayKeeper, 'clean_sheets'));
    }

    public function test_keeper_subbed_on_in_extra_time_only_carries_et_goals(): void
    {
        $starter = $this->keeper($this->home);
        $sub = $this->keeper($this->home);

        // Cambio de portera en el 95' (prórroga); el gol cae en la ET.
        $match = $this->match(
            homeScore: 0,
            awayScore: 0,
            homeLineup: [$starter->id],
            awayLineup: [$this->keeper($this->away)->id],
            substitutions: [[
                'team_id' => $this->home->id,
                'player_out_id' => $starter->id,
                'player_in_id' => $sub->id,
                'minute' => 95,
            ]],
            extraTime: ['home' => 0, 'away' => 1],
        );

        $this->handle($match);

        // 5' vs 25' en la ET → el gol lo carga la suplente.
        $this->assertSame(0, $this->stat($starter, 'goals_conceded'));
        $this->assertSame(1, $this->stat($sub, 'goals_conceded'));
        $this->assertSame(0, $this->stat($starter, 'clean_sheets'));
        $this->assertSame(0, $this->stat($sub, 'clean_sheets'));
    }

    private function keeper(Team $team): GamePlayer
    {
        return GamePlayer::factory()->goalkeeper()->create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
        ]);
    }

    /**
     * @param string[] $homeLineup
     * @param string[] $awayLineup
     */
    private function match(
        int $homeScore,
        int $awayScore,
        array $homeLineup,
        array $awayLineup,
        array $substitutions = [],
        ?array $extraTime = null,
    ): GameMatch {
        $attrs = [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => true,
            'home_lineup' => $homeLineup,
            'away_lineup' => $awayLineup,
            'substitutions' => $substitutions,
        ];

        if ($extraTime !== null) {
            $attrs['is_extra_time'] = true;
            $attrs['home_score_et'] = $extraTime['home'];
            $attrs['away_score_et'] = $extraTime['away'];
        }

        return GameMatch::factory()->create($attrs);
    }

    private function handle(GameMatch $match): void
    {
        app(UpdateGoalkeeperStats::class)->handle(new MatchFinalized($match, $this->game, $this->competition));
    }

    private function stat(GamePlayer $player, string $column): int
    {
        return (int) GamePlayerMatchState::where('game_player_id', $player->id)->value($column);
    }
}

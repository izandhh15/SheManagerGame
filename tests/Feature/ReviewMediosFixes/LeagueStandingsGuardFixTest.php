<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Match\Listeners\UpdateLeagueStandings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 12 de la revisión de medios (grupo 08): el guard standings_applied
 * era check-then-set sin transacción — dos finalizaciones concurrentes del
 * mismo partido pasaban el guard y la clasificación contaba doble. Ahora el
 * check-then-set corre en transacción con lock de fila.
 */
class LeagueStandingsGuardFixTest extends TestCase
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

        foreach ([$this->home, $this->away] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'position' => 1,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }
    }

    public function test_double_finalization_applies_standings_only_once(): void
    {
        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_score' => 2,
            'away_score' => 1,
            'played' => true,
            'standings_applied' => false,
        ]);

        $listener = app(UpdateLeagueStandings::class);

        // Dos finalizaciones del mismo partido (simula la concurrencia: el
        // segundo handle debe ver el flag ya a true).
        $listener->handle(new MatchFinalized($match, $this->game, $this->competition));
        $listener->handle(new MatchFinalized($match->fresh(), $this->game, $this->competition));

        $homeStanding = GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP1')
            ->where('team_id', $this->home->id)
            ->firstOrFail();

        // Con el bug (sin lock) el segundo handle reaplicaba: played = 2.
        $this->assertSame(1, (int) $homeStanding->played);
        $this->assertSame(1, (int) $homeStanding->won);
        $this->assertSame(3, (int) $homeStanding->points);
        $this->assertSame(2, (int) $homeStanding->goals_for);
        $this->assertSame(1, (int) $homeStanding->goals_against);

        $this->assertTrue((bool) $match->fresh()->standings_applied);
    }

    public function test_already_applied_match_is_skipped(): void
    {
        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_score' => 2,
            'away_score' => 1,
            'played' => true,
            'standings_applied' => true,
        ]);

        app(UpdateLeagueStandings::class)->handle(new MatchFinalized($match, $this->game, $this->competition));

        // El partido ya estaba aplicado: la clasificación no debe moverse.
        $this->assertSame(
            0,
            (int) GameStanding::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP1')
                ->where('team_id', $this->home->id)
                ->value('played'),
            'se aplicó la clasificación de un partido ya aplicado'
        );
    }
}

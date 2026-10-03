<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ManagerPressureService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 3 de la revisión de medios: ManagerPressureService::lastMatchLine()
 * afirmaba "derrota" aunque el último partido se hubiera ganado o empatado
 * (el trigger points<=4 salta también con rachas tipo L,L,L,D,W).
 * Ahora la línea refleja el resultado real del último partido.
 */
class ManagerPressureFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_match_line_reports_a_win(): void
    {
        [$game, $team] = $this->basicGame();
        $match = $this->playedMatch($game, $team, homeScore: 3, awayScore: 0, teamIsHome: true);

        $line = $this->lastMatchLine($team, $match);

        $this->assertStringContainsString('victoria', $line);
        $this->assertStringNotContainsString('derrota', $line);
    }

    public function test_last_match_line_reports_an_away_win(): void
    {
        [$game, $team] = $this->basicGame();
        $match = $this->playedMatch($game, $team, homeScore: 1, awayScore: 2, teamIsHome: false);

        $line = $this->lastMatchLine($team, $match);

        $this->assertStringContainsString('victoria', $line);
        $this->assertStringNotContainsString('derrota', $line);
    }

    public function test_last_match_line_reports_a_draw(): void
    {
        [$game, $team] = $this->basicGame();
        $match = $this->playedMatch($game, $team, homeScore: 1, awayScore: 1, teamIsHome: true);

        $line = $this->lastMatchLine($team, $match);

        $this->assertStringContainsString('empate', $line);
        $this->assertStringNotContainsString('derrota', $line);
    }

    public function test_last_match_line_still_reports_a_defeat(): void
    {
        [$game, $team] = $this->basicGame();
        $match = $this->playedMatch($game, $team, homeScore: 0, awayScore: 2, teamIsHome: true);

        $line = $this->lastMatchLine($team, $match);

        $this->assertStringContainsString('derrota', $line);
    }

    public function test_last_match_line_english_win(): void
    {
        app()->setLocale('en');
        [$game, $team] = $this->basicGame();
        $match = $this->playedMatch($game, $team, homeScore: 2, awayScore: 0, teamIsHome: true);

        $line = $this->invokePrivate('lastMatchLine', [false, $team, $match]);

        $this->assertStringContainsString('victory', $line);
        $this->assertStringNotContainsString('defeat', $line);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{Game, Team} */
    private function basicGame(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $team];
    }

    private function playedMatch(Game $game, Team $team, int $homeScore, int $awayScore, bool $teamIsHome): GameMatch
    {
        $rival = Team::factory()->create(['name' => 'Rival WFC', 'country' => 'ES']);

        return GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $teamIsHome ? $team->id : $rival->id,
            'away_team_id' => $teamIsHome ? $rival->id : $team->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => true,
            'scheduled_date' => Carbon::parse('2026-09-20'),
        ]);
    }

    private function lastMatchLine(Team $team, GameMatch $match): string
    {
        return $this->invokePrivate('lastMatchLine', [true, $team, $match]);
    }

    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(ManagerPressureService::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke(app(ManagerPressureService::class), ...$args);
    }
}

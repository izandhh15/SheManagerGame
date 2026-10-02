<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CupTieCardByeTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
        ]);
    }

    private function makeTie(Team $home, Team $away): CupTie
    {
        $tie = new CupTie([
            'game_id' => $this->game->id,
            'competition_id' => 'ESPCUP',
            'round_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'completed' => $home->id === $away->id,
            'winner_id' => $home->id === $away->id ? $home->id : null,
        ]);
        // The card reads the round config through the game relation.
        $tie->setRelation('game', $this->game);
        $tie->setRelation('homeTeam', $home);
        $tie->setRelation('awayTeam', $away);

        return $tie;
    }

    public function test_bye_tie_renders_as_exento_not_a_self_matchup(): void
    {
        $team = Team::factory()->create(['country' => 'ES', 'name' => 'CD Prueba Bye']);

        // A bye is stored as a tie of a team against itself (no opponent).
        $tie = $this->makeTie($team, $team);

        $html = Blade::render(
            '<x-cup-tie-card :tie="$tie" :playerTeamId="$playerTeamId" />',
            ['tie' => $tie, 'playerTeamId' => 'some-other-team'],
        );

        $this->assertStringContainsString(__('cup.bye'), $html);
        $this->assertStringNotContainsString(
            'border-t border-border-default',
            $html,
            'no away-team row — the bye is a single team, not "X vs X"',
        );
    }

    public function test_normal_tie_still_renders_both_teams(): void
    {
        $home = Team::factory()->create(['country' => 'ES', 'name' => 'CD Local Normal']);
        $away = Team::factory()->create(['country' => 'ES', 'name' => 'CD Visitante Normal']);

        $tie = $this->makeTie($home, $away);

        $html = Blade::render(
            '<x-cup-tie-card :tie="$tie" :playerTeamId="$playerTeamId" />',
            ['tie' => $tie, 'playerTeamId' => 'some-other-team'],
        );

        $this->assertStringContainsString('CD Local Normal', $html);
        $this->assertStringContainsString('CD Visitante Normal', $html);
        $this->assertStringNotContainsString(__('cup.bye'), $html);
    }
}

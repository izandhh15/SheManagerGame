<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Http\Actions\RequestParentStadium;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Fix 19: RequestParentStadium filters the parent's home matches by game_id —
 * a home match of the same parent club in ANOTHER user's save must not
 * block the request.
 */
class RequestParentStadiumTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $firstTeam;
    private Team $parentTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'ESP1']);
        Competition::factory()->create(['id' => 'ESP2']);

        $this->user = User::factory()->create();
        $this->parentTeam = Team::factory()->create([
            'country' => 'ES',
            'stadium_name' => 'Parent Stadium',
            'stadium_seats' => 40000,
        ]);
        // The user's team is the filial: its parent is the first team.
        $this->firstTeam = Team::factory()->create([
            'country' => 'ES',
            'parent_team_id' => $this->parentTeam->id,
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->firstTeam->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function homeMatch(): GameMatch
    {
        return GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'home_team_id' => $this->firstTeam->id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'played' => false,
            'scheduled_date' => '2026-09-20',
        ]);
    }

    private function request(GameMatch $match): Request
    {
        $request = Request::create("/game/{$this->game->id}/parent-stadium", 'POST', [
            'match_id' => $match->id,
        ]);
        $request->setUserResolver(fn () => $this->user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_parent_busy_in_another_game_does_not_block(): void
    {
        $otherGame = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        // The parent club plays at home on the same day — but in the OTHER game.
        GameMatch::factory()->create([
            'game_id' => $otherGame->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->parentTeam->id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'played' => false,
            'scheduled_date' => '2026-09-20',
        ]);

        $match = $this->homeMatch();
        $action = app(RequestParentStadium::class);
        $response = $action($this->request($match), $this->game->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('success'));
        $this->assertSame('Parent Stadium', $match->fresh()->neutral_venue_name);
    }

    public function test_parent_busy_in_same_game_blocks(): void
    {
        // The parent club really is busy that day in THIS game.
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->parentTeam->id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'played' => false,
            'scheduled_date' => '2026-09-20',
        ]);

        $match = $this->homeMatch();
        $action = app(RequestParentStadium::class);
        $response = $action($this->request($match), $this->game->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('error'));
        $this->assertNull($match->fresh()->neutral_venue_name);
    }
}

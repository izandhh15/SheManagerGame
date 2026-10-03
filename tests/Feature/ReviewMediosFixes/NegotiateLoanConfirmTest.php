<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Http\Actions\NegotiateLoan;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Fix 17: NegotiateLoan::handleConfirm() re-checks isUserOwned($game).
 * A player bought between "start" and "confirm" (e.g. in another tab) must
 * not produce a loan offer of the club to itself.
 */
class NegotiateLoanConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function confirmRequest(): Request
    {
        $request = Request::create(
            "/game/{$this->game->id}/loan/negotiate/x",
            'POST',
            ['action' => 'confirm']
        );
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    public function test_confirm_rejects_player_that_became_user_owned(): void
    {
        // Between start and confirm, the user bought the player.
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
        ]);

        $action = app(NegotiateLoan::class);
        $response = $action($this->confirmRequest(), $this->game->id, $player->id);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function test_confirm_rejects_player_without_team(): void
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => null,
        ]);

        $action = app(NegotiateLoan::class);
        $response = $action($this->confirmRequest(), $this->game->id, $player->id);

        $this->assertSame(422, $response->getStatusCode());
    }
}

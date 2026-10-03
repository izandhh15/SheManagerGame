<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Squad\Listeners\CheckRecoveredPlayers;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for QA bug A4: AI-team players never recovered from
 * injuries because CheckRecoveredPlayers filtered to the user's team only.
 * See ~/workspace/shemanager/qa/bugs/agent-10.md.
 */
class QaA4AiInjuryRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-17',
        ]);
    }

    public function test_ai_team_player_recovers_on_date_advance(): void
    {
        $aiTeam = Team::factory()->create();

        $aiPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($aiTeam)
            ->create([
                'name' => 'Rival Winger',
                'injury_until' => '2025-08-05',
                'injury_type' => 'ACL tear',
            ]);

        $this->dispatchAdvanceTo('2025-07-30', '2025-08-17');

        // The injury is cleared even though the player is not on the user's team.
        $this->assertNull($aiPlayer->refresh()->injury_until);
        $this->assertNull($aiPlayer->refresh()->injury_type);

        // Rival squads recover silently: no recovery notification in the user's feed.
        $this->assertSame(0, GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_RECOVERED)
            ->count());
    }

    public function test_user_team_player_still_recovers_with_notification(): void
    {
        $userPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'My Striker',
                'injury_until' => '2025-08-05',
                'injury_type' => 'Muscle strain',
            ]);

        $this->dispatchAdvanceTo('2025-07-30', '2025-08-17');

        // The user's player recovers exactly as before the fix.
        $this->assertNull($userPlayer->refresh()->injury_until);
        $this->assertNull($userPlayer->refresh()->injury_type);

        // …and the recovery notification semantics are unchanged: dated to
        // the actual return date, not the forward-looking current_date.
        $notification = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_RECOVERED)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($userPlayer->id, $notification->metadata['player_id']);
        $this->assertSame('2025-08-05', $notification->game_date->toDateString());
    }

    private function dispatchAdvanceTo(string $previous, string $new): void
    {
        $event = new GameDateAdvanced(
            $this->game,
            Carbon::parse($previous),
            Carbon::parse($new),
        );

        app(CheckRecoveredPlayers::class)->handle($event);
    }
}

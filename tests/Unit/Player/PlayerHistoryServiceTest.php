<?php

namespace Tests\Unit\Player;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Player\Services\PlayerHistoryService;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PlayerHistoryService: club history is derived ONLY from the game's
 * transfer ledger (game_transfers). No pre-game history is invented.
 */
class PlayerHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlayerHistoryService
    {
        return app(PlayerHistoryService::class);
    }

    private function buildGame(Team $team): Game
    {
        $user = User::factory()->create();

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function recordTransfer(Game $game, GamePlayer $player, ?string $from, string $to, string $season = '2026'): void
    {
        GameTransfer::record(
            $game->id,
            $player->id,
            $from,
            $to,
            1000000,
            GameTransfer::TYPE_TRANSFER,
            $season,
            'summer',
        );
    }

    public function test_no_transfers_history_is_current_club_only(): void
    {
        $team = Team::factory()->create();
        $game = $this->buildGame($team);
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
        ]);

        $history = $this->service()->clubHistory($game, $player);

        $this->assertEquals([$team->id], $history->all());
        $this->assertTrue($this->service()->formerClubIds($game, $player)->isEmpty());
        $this->assertFalse($this->service()->returnsHomeAgainst($game, $player, Team::factory()->create()->id));
    }

    public function test_single_transfer_builds_two_club_history(): void
    {
        $valencia = Team::factory()->create(['name' => 'Valencia CF Femenino']);
        $madrid = Team::factory()->create(['name' => 'Real Madrid CF']);
        $game = $this->buildGame($madrid);
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $madrid->id,
            'name' => 'Claudia Florentino',
        ]);

        $this->recordTransfer($game, $player, $valencia->id, $madrid->id);

        $service = $this->service();
        $this->assertEquals(
            [$valencia->id, $madrid->id],
            $service->clubHistory($game, $player)->all()
        );
        $this->assertEquals([$valencia->id], $service->formerClubIds($game, $player)->all());
        $this->assertTrue($service->returnsHomeAgainst($game, $player, $valencia->id));
        $this->assertFalse($service->returnsHomeAgainst($game, $player, Team::factory()->create()->id));
        // Facing her own club is never "returning home".
        $this->assertFalse($service->returnsHomeAgainst($game, $player, $madrid->id));
    }

    public function test_returning_player_keeps_full_path(): void
    {
        // Valencia -> Madrid -> Valencia (vuelve a casa).
        $valencia = Team::factory()->create(['name' => 'Valencia CF Femenino']);
        $madrid = Team::factory()->create(['name' => 'Real Madrid CF']);
        $game = $this->buildGame($valencia);
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $valencia->id,
            'name' => 'Claudia Florentino',
        ]);

        $this->recordTransfer($game, $player, $valencia->id, $madrid->id, '2026');
        $this->recordTransfer($game, $player, $madrid->id, $valencia->id, '2027');

        $service = $this->service();
        $this->assertEquals(
            [$valencia->id, $madrid->id, $valencia->id],
            $service->clubHistory($game, $player)->all()
        );
        // She already played for Valencia before: signing her is a homecoming.
        $this->assertTrue($service->isHomecomingSigning($game, $player, $valencia->id));
        // She also played for Madrid before (middle stint): that would be
        // a homecoming too if Madrid re-signed her.
        $this->assertTrue($service->isHomecomingSigning($game, $player, $madrid->id));
        $other = Team::factory()->create();
        $this->assertFalse($service->isHomecomingSigning($game, $player, $other->id));
        // Former clubs exclude the current one.
        $this->assertEqualsCanonicalizing(
            [$valencia->id, $madrid->id],
            $service->formerClubIds($game, $player)->all()
        );
    }

    public function test_first_time_signing_is_not_a_homecoming(): void
    {
        $valencia = Team::factory()->create();
        $madrid = Team::factory()->create();
        $game = $this->buildGame($madrid);
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $madrid->id,
        ]);

        $this->recordTransfer($game, $player, $valencia->id, $madrid->id);

        $this->assertFalse($this->service()->isHomecomingSigning($game, $player, $madrid->id));
    }

    public function test_free_agent_transfer_without_from_team(): void
    {
        $madrid = Team::factory()->create();
        $game = $this->buildGame($madrid);
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $madrid->id,
        ]);

        $this->recordTransfer($game, $player, null, $madrid->id);

        $history = $this->service()->clubHistory($game, $player);
        $this->assertEquals([$madrid->id], $history->all());
    }

    public function test_preview_highlights_homecoming_player(): void
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $valencia = Team::factory()->create(['name' => 'Valencia CF Femenino', 'country' => 'ES']);
        $madrid = Team::factory()->create(['name' => 'Real Madrid CF', 'country' => 'ES']);
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $valencia->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        // Claudia: Valencia -> Madrid -> Valencia (now faces Madrid again).
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $valencia->id,
            'name' => 'Claudia Florentino',
            'overall_score' => 85,
        ]);
        $this->recordTransfer($game, $player, $valencia->id, $madrid->id, '2026');
        $this->recordTransfer($game, $player, $madrid->id, $valencia->id, '2027');

        $cupTie = CupTie::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $valencia->id,
            'away_team_id' => $madrid->id,
        ]);
        $match = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $valencia->id,
            'away_team_id' => $madrid->id,
            'played' => false,
            'round_number' => 7,
            'cup_tie_id' => $cupTie->id,
            'scheduled_date' => Carbon::parse('2026-10-17'),
        ]);

        $articles = app(PressNewsService::class)->articles($game->refresh(), $match);
        $preview = collect($articles)->firstWhere('category', 'preview');

        $this->assertNotNull($preview, 'A cup tie must produce a previa article.');
        $body = implode(' ', $preview->body);
        $this->assertStringContainsString('Claudia Florentino', $body);
        $this->assertStringContainsString('vuelve a casa', $body);
    }
}

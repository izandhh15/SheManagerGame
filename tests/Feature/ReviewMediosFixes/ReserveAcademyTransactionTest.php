<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\User;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fixes 2-3: the reserve/academy moves run in DB transactions.
 * Happy paths must keep working inside the transaction; failures must
 * roll everything back.
 */
class ReserveAcademyTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $firstTeam;
    private Team $reserveTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->firstTeam = Team::factory()->create(['country' => 'ES']);
        $this->reserveTeam = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_call_up_to_first_team_is_atomic(): void
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->reserveTeam->id,
            'is_stand_in' => false,
            'date_of_birth' => '2005-03-10',
        ]);

        $service = app(ReserveTeamService::class);
        $service->callUpToFirstTeam($player, $this->game);

        $fresh = $player->fresh();
        $this->assertSame($this->firstTeam->id, $fresh->team_id);
        $this->assertNotNull($fresh->number);
        $this->assertDatabaseHas('loans', [
            'game_player_id' => $player->id,
            'status' => Loan::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('game_transfers', [
            'game_player_id' => $player->id,
            'type' => 'loan',
        ]);
    }

    public function test_send_down_to_reserve_is_atomic(): void
    {
        // 18 first-team players so the squad-minimum guard (17) passes.
        for ($i = 0; $i < 17; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->firstTeam->id,
            ]);
        }
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->firstTeam->id,
            'number' => 15,
            'date_of_birth' => '2006-05-20',
        ]);

        $service = app(ReserveTeamService::class);
        $service->sendDownToReserve($player, $this->game);

        $fresh = $player->fresh();
        $this->assertSame($this->reserveTeam->id, $fresh->team_id);
        $this->assertNull($fresh->number);
        $this->assertDatabaseHas('game_transfers', [
            'game_player_id' => $player->id,
            'type' => 'internal_demotion',
        ]);
    }

    public function test_promote_academy_player_is_atomic(): void
    {
        $academy = AcademyPlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'team_id' => $this->firstTeam->id,
            'name' => 'Test Prospect',
            'nationality' => ['ES'],
            'date_of_birth' => '2008-04-01',
            'position' => 'Central Midfield',
            'overall_score' => 62,
            'potential' => 85,
            'potential_low' => 78,
            'potential_high' => 90,
            'appeared_at' => '2026-08-15',
        ]);

        $service = app(YouthAcademyService::class);
        $gamePlayer = $service->promoteToFirstTeam($academy, $this->game);

        $this->assertSame($this->firstTeam->id, $gamePlayer->team_id);
        $this->assertNotNull($gamePlayer->number);
        // The academy row is gone: the player must not exist in both places.
        $this->assertDatabaseMissing('academy_players', ['id' => $academy->id]);
        $this->assertDatabaseHas('game_players', ['id' => $gamePlayer->id]);
    }
}

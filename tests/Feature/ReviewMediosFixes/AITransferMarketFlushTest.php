<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\AITransferMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fix 15: AITransferMarketService::flushBatchedOperations() wraps the
 * upserts + transfer inserts in a DB transaction, so a partial failure
 * can't leave players moved without their ledger row.
 */
class AITransferMarketFlushTest extends TestCase
{
    use RefreshDatabase;

    public function test_flush_applies_updates_and_inserts_together(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create(['country' => 'ES']);
        $teamB = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $teamA->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $teamA->id,
            'number' => 7,
            'contract_until' => '2027-06-30',
            'annual_wage' => 1_000_000_00,
        ]);

        $service = app(AITransferMarketService::class);
        $method = new \ReflectionMethod($service, 'flushBatchedOperations');
        $method->setAccessible(true);

        $method->invoke($service, [
            [
                'id' => $player->id,
                'game_id' => $game->id,
                'player_id' => $player->player_id,
                'position' => $player->position,
                'team_id' => $teamB->id,
                'number' => 9,
                'contract_until' => '2028-06-30',
                'annual_wage' => 1_200_000_00,
                'release_clause' => null,
            ],
        ], [
            [
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'from_team_id' => $teamA->id,
                'to_team_id' => $teamB->id,
                'transfer_fee' => 5_000_000_00,
                'type' => 'transfer',
                'season' => '2026',
                'window' => 'summer',
            ],
        ]);

        $fresh = $player->fresh();
        $this->assertSame($teamB->id, $fresh->team_id);
        $this->assertSame(9, (int) $fresh->number);
        $this->assertDatabaseHas('game_transfers', [
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'from_team_id' => $teamA->id,
            'to_team_id' => $teamB->id,
        ]);
    }
}

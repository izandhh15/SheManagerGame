<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Transfer\Services\TransferCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: TransferCompletionService::completePreContractTransfer()
 * ignored the negotiated `offered_wage` (the twin completion paths at
 * :289/:370 both apply it), so a Bosman signing kept her old salary
 * instead of the agreed one.
 *
 * The rand(2, 4) contract length is intentionally kept — it mirrors the
 * rand(3, 5) fallback in the twin paths (varied contract lengths).
 */
class PreContractWageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_contract_applies_the_negotiated_wage(): void
    {
        $sellingTeam = Team::factory()->create(['name' => 'Selling FC']);
        $buyingTeam = Team::factory()->create(['name' => 'Buying FC']);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'team_id' => $sellingTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        $player = GamePlayer::factory()
            ->forGame($game)
            ->forTeam($sellingTeam)
            ->create([
                'date_of_birth' => '1998-06-15',
                'annual_wage' => 500_000,
            ]);

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyingTeam->id,
            'selling_team_id' => $sellingTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 900_000,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date->copy()->addDays(14),
            'game_date' => $game->current_date,
        ]);

        app(TransferCompletionService::class)->completePreContractTransfer($offer);

        $player->refresh();
        $this->assertSame($buyingTeam->id, $player->team_id);
        $this->assertSame(900_000, (int) $player->annual_wage, 'pre-contract must honour the negotiated wage');
    }

    public function test_pre_contract_keeps_current_wage_when_no_wage_was_negotiated(): void
    {
        $sellingTeam = Team::factory()->create(['name' => 'Selling FC']);
        $buyingTeam = Team::factory()->create(['name' => 'Buying FC']);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'team_id' => $sellingTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        $player = GamePlayer::factory()
            ->forGame($game)
            ->forTeam($sellingTeam)
            ->create([
                'date_of_birth' => '1998-06-15',
                'annual_wage' => 500_000,
            ]);

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyingTeam->id,
            'selling_team_id' => $sellingTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => null,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date->copy()->addDays(14),
            'game_date' => $game->current_date,
        ]);

        app(TransferCompletionService::class)->completePreContractTransfer($offer);

        $player->refresh();
        $this->assertSame(500_000, (int) $player->annual_wage, 'null offered_wage must fall back to the current wage (column is NOT NULL)');
    }
}

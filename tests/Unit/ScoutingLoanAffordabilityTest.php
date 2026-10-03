<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: ScoutingService::getPlayerScoutingDetail() compared the
 * ANNUAL wage demand against the TRANSFER budget for `can_afford_loan`.
 * The game has no wage-budget scalar (wages follow the revenue-based club
 * wage level), so the comparison basis is now labelled explicitly and
 * pinned here: annual wage demand vs available transfer budget, display
 * only. A free agent is always "affordable".
 */
class ScoutingLoanAffordabilityTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userTeam = Team::factory()->create(['name' => 'User FC']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);
    }

    public function test_free_agent_is_always_affordable(): void
    {
        $this->setTransferBudget(0);

        $freeAgent = GamePlayer::factory()
            ->forGame($this->game)
            ->create(['team_id' => null, 'date_of_birth' => '1998-06-15']);

        $detail = app(ScoutingService::class)->getPlayerScoutingDetail($freeAgent, $this->game);

        $this->assertTrue($detail['can_afford_loan']);
    }

    public function test_wage_demand_above_transfer_budget_is_not_affordable(): void
    {
        $player = $this->listedPlayer();
        $wageDemand = $this->wageDemand($player);

        $this->setTransferBudget(max(0, $wageDemand - 1));

        $detail = app(ScoutingService::class)->getPlayerScoutingDetail($player, $this->game);

        $this->assertSame($wageDemand, $detail['wage_demand']);
        $this->assertFalse($detail['can_afford_loan'], 'annual wage demand above the transfer budget must read as unaffordable');
    }

    public function test_wage_demand_within_transfer_budget_is_affordable(): void
    {
        $player = $this->listedPlayer();
        $wageDemand = $this->wageDemand($player);

        $this->setTransferBudget($wageDemand);

        $detail = app(ScoutingService::class)->getPlayerScoutingDetail($player, $this->game);

        $this->assertTrue($detail['can_afford_loan']);
    }

    private function listedPlayer(): GamePlayer
    {
        $aiTeam = Team::factory()->create(['name' => 'AI FC']);

        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($aiTeam)
            ->create(['date_of_birth' => '1998-06-15', 'overall_score' => 75]);
    }

    private function wageDemand(GamePlayer $player): int
    {
        return (int) app(ContractService::class)
            ->calculateWageDemand($player, NegotiationScenario::TRANSFER, $this->userTeam)['wage'];
    }

    private function setTransferBudget(int $amount): void
    {
        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => (int) $this->game->season,
            'transfer_budget' => $amount,
        ]);
    }
}

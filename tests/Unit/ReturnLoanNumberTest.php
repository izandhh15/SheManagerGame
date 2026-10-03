<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Modules\Transfer\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: LoanService::returnLoan() gated squad-number assignment on
 * `$isUserTeam` (parent === team_id), which mislabelled the reserve team
 * as "not the user's team". The real rule — now named `$returnsToFirstTeam`
 * — is that only the first-team roster uses squad numbers: returns to the
 * filial or to AI clubs null the number by design (the reserve's
 * number=null invariant; numbers are assigned on call-up).
 */
class ReturnLoanNumberTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $firstTeam;
    private Team $reserveTeam;
    private Team $aiTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firstTeam = Team::factory()->create(['name' => 'Atlético de Madrid']);
        $this->reserveTeam = Team::factory()->create([
            'name' => 'Atlético Madrileño',
            'parent_team_id' => $this->firstTeam->id,
        ]);
        $this->aiTeam = Team::factory()->create(['name' => 'AI FC']);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'team_id' => $this->firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);
    }

    public function test_return_to_first_team_assigns_a_squad_number(): void
    {
        $player = $this->loanedPlayer($this->firstTeam, $this->aiTeam);

        app(LoanService::class)->returnLoan($player->activeLoan);

        $player->refresh();
        $this->assertSame($this->firstTeam->id, $player->team_id);
        $this->assertNotNull($player->number, 'returning to the first team must assign a squad number');
    }

    public function test_return_to_reserve_team_nulls_the_number_by_design(): void
    {
        // Filial invariant: reserve players wear no numbers; the number is
        // assigned when the player is called up to the first team.
        $player = $this->loanedPlayer($this->reserveTeam, $this->firstTeam);

        app(LoanService::class)->returnLoan($player->activeLoan);

        $player->refresh();
        $this->assertSame($this->reserveTeam->id, $player->team_id);
        $this->assertNull($player->number, 'returning to the reserve must null the number (filial invariant)');
    }

    public function test_return_to_ai_team_nulls_the_number(): void
    {
        $player = $this->loanedPlayer($this->aiTeam, $this->firstTeam);

        app(LoanService::class)->returnLoan($player->activeLoan);

        $player->refresh();
        $this->assertSame($this->aiTeam->id, $player->team_id);
        $this->assertNull($player->number);
    }

    /**
     * A player physically at $loanTeamId on an active loan whose parent is
     * $parentTeamId.
     */
    private function loanedPlayer(Team $parentTeam, Team $loanTeam): GamePlayer
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($loanTeam)
            ->create(['date_of_birth' => '1998-06-15', 'number' => 9]);

        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parentTeam->id,
            'loan_team_id' => $loanTeam->id,
            'started_at' => $this->game->current_date,
            'return_at' => $this->game->current_date->copy()->addMonths(6),
            'status' => Loan::STATUS_ACTIVE,
        ]);

        return $player;
    }
}

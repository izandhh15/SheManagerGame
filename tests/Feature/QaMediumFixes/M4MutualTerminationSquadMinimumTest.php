<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\MutualTerminationNegotiation;
use App\Models\Team;
use App\Models\User;
use App\Modules\Finance\Services\SeverancePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M4: la rescisión de mutuo acuerdo no aplicaba el mínimo de
 * plantilla (17): con 17 jugadoras se podía pactar y completar, dejando la
 * plantilla en 16.
 *
 * Bug original: NegotiateMutualTermination::validateEligibility() decía
 * "Misma elegibilidad que la liberación unilateral" pero omitía la llamada
 * a SquadMinimumService::validateRemoval() que ContractService::validateRelease()
 * sí hace. Ni el inicio de la negociación ni (antes del fix A8) el completar
 * lo comprobaban.
 *
 * Fix: validateEligibility() aplica ahora SquadMinimumService::validateRemoval()
 * al iniciar la negociación, igual que la vía unilateral. El completar ya lo
 * revalida (fix A8, TOCTOU): este test lo bloquea también como guardia.
 */
class M4MutualTerminationSquadMinimumTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'M4 User Team']);

        $competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $competition->id,
            'current_date' => '2026-08-01',
            'season' => '2026',
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);
    }

    private function fillFirstTeam(int $size): void
    {
        for ($i = 1; $i <= $size; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->userTeam->id,
                'position' => $i <= 2 ? 'Goalkeeper' : 'Central Midfield',
                'number' => $i,
                'market_value_cents' => 5_000_000_00,
                'contract_until' => '2028-06-30',
                'annual_wage' => 1_000_000_00,
            ]);
        }
    }

    private function squadCount(): int
    {
        return GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->count();
    }

    private function firstTeamPlayer(): GamePlayer
    {
        return GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->where('number', 10)
            ->firstOrFail();
    }

    private function negotiateStart(GamePlayer $player)
    {
        return $this->actingAs($this->user)->postJson(
            route('game.negotiate.mutual-termination', [$this->game->id, $player->id]),
            ['action' => 'start']
        );
    }

    /**
     * Con la plantilla en el mínimo (17), ni siquiera se puede INICIAR la
     * negociación de mutuo acuerdo: 422 y la plantilla intacta.
     */
    public function test_mutual_termination_negotiation_blocked_at_squad_minimum(): void
    {
        $this->fillFirstTeam(17);
        $this->assertSame(17, $this->squadCount());

        $response = $this->negotiateStart($this->firstTeamPlayer());

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $this->assertSame(17, $this->squadCount(), 'La plantilla no debe tocarse');
        $this->assertSame(
            0,
            MutualTerminationNegotiation::where('game_id', $this->game->id)->count(),
            'No debe crearse ninguna negociación'
        );
    }

    /**
     * Con 18 jugadoras el inicio de la negociación sigue permitido (el
     * guardia no sobrerreacciona).
     */
    public function test_mutual_termination_negotiation_allowed_above_minimum(): void
    {
        $this->fillFirstTeam(18);

        $response = $this->negotiateStart($this->firstTeamPlayer());

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
    }

    /**
     * Aunque una negociación llegase a AGREED con la plantilla ya en 17
     * (p. ej. pactada con 18 y una salida posterior), completarla debe
     * bloquearse: la plantilla nunca baja a 16.
     */
    public function test_mutual_termination_completion_blocked_at_squad_minimum(): void
    {
        $this->fillFirstTeam(17);
        $player = $this->firstTeamPlayer();

        MutualTerminationNegotiation::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'status' => MutualTerminationNegotiation::STATUS_AGREED,
            'round' => 1,
            'agent_demand' => 500_000_00,
            'agreed_amount' => 500_000_00,
        ]);

        $response = $this->actingAs($this->user)->post(
            route('game.squad.mutual-termination.complete', [$this->game->id, $player->id]),
            ['payment_method' => SeverancePaymentService::METHOD_LUMP_SUM]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(17, $this->squadCount(), 'La plantilla debe seguir en 17');
        $this->assertSame($this->userTeam->id, $player->fresh()->team_id, 'La jugadora no debe liberarse');
    }
}

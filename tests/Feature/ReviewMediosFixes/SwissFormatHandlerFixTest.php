<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use App\Modules\Match\Handlers\SwissFormatHandler;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Competition\Services\NeutralVenueResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 10 de la revisión de medios (grupo 07): EXPECTED_TIES_PER_ROUND
 * hardcodeado — la siguiente ronda solo se generaba si el nº de ties
 * completados coincidía EXACTO con el esperado, así que una ronda con menos
 * ties (top-up incompleto) atascaba la competición para siempre. Ahora la
 * ronda se considera completa cuando todos SUS ties están decididos.
 */
class SwissFormatHandlerFixTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private SwissFormatHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->knockoutCup()->create([
            'id' => 'UCL',
            'country' => 'EU',
            'handler_type' => 'swiss_format',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'EU',
            'team_id' => Team::factory()->create(['country' => 'EU'])->id,
            'competition_id' => 'UCL',
            'current_date' => Carbon::parse('2027-03-01'),
        ]);

        $this->handler = new SwissFormatHandler(
            app(CupTieResolver::class),
            app(EligibilityService::class),
            app(NeutralVenueResolver::class),
            $this->fakeGenerator(),
        );
    }

    public function test_next_round_generates_from_fewer_ties_than_the_old_hardcoded_count(): void
    {
        // 3 ties decididos en el playoff (el antiguo hardcoded exigía 8).
        $winners = [];
        for ($i = 0; $i < 3; $i++) {
            $winners[] = $this->decidedTie($i)->winner_id;
        }

        $this->maybeGenerate();

        $this->assertTrue(
            CupTie::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('round_number', SwissKnockoutGenerator::ROUND_OF_16)
                ->exists(),
            'la siguiente ronda no se generó con 3 ties completados'
        );
    }

    public function test_next_round_waits_for_undecided_ties(): void
    {
        $this->decidedTie(0);
        $this->decidedTie(1);
        // Un tie sin decidir: la ronda NO está completa.
        CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UCL',
            'round_number' => SwissKnockoutGenerator::ROUND_KNOCKOUT_PLAYOFF,
            'completed' => false,
        ]);

        $this->maybeGenerate();

        $this->assertFalse(
            CupTie::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('round_number', SwissKnockoutGenerator::ROUND_OF_16)
                ->exists(),
            'se generó la siguiente ronda con un tie sin decidir'
        );
    }

    private function decidedTie(int $index): CupTie
    {
        $home = Team::factory()->create(['country' => 'EU']);
        $away = Team::factory()->create(['country' => 'EU']);

        return CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UCL',
            'round_number' => SwissKnockoutGenerator::ROUND_KNOCKOUT_PLAYOFF,
            'bracket_position' => $index,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'completed' => true,
            'winner_id' => $home->id,
        ]);
    }

    private function maybeGenerate(): void
    {
        $ref = new \ReflectionMethod(SwissFormatHandler::class, 'maybeGenerateKnockoutRound');
        $ref->setAccessible(true);
        $ref->invoke($this->handler, $this->game, 'UCL');
    }

    private function fakeGenerator(): SwissKnockoutGenerator
    {
        return new class extends SwissKnockoutGenerator {
            public function getRoundConfig(int $round, string $competitionId, Game $game): PlayoffRoundConfig
            {
                return new PlayoffRoundConfig(
                    round: $round,
                    name: 'Test round',
                    twoLegged: false,
                    firstLegDate: Carbon::parse('2027-04-01'),
                );
            }

            public function generateMatchups(Game $game, string $competitionId, int $round): array
            {
                $home = Team::factory()->create(['country' => 'EU']);
                $away = Team::factory()->create(['country' => 'EU']);

                return [[$home->id, $away->id, 1]];
            }
        };
    }
}

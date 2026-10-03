<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Listeners\ConductNextCupRoundDraw;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Fix 11 de la revisión de medios (grupo 07): el listener solo capturaba
 * OddCupDrawPoolException; cualquier otra excepción de conductDraw() se
 * propagaba dentro de la transacción del avance → rollback total y usuario
 * atascado reintentando. Ahora captura cualquier excepción: la copa deja de
 * sortear rondas pero la finalización del partido no revienta.
 */
class ConductNextCupRoundDrawFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_unexpected_draw_exception_does_not_propagate(): void
    {
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'competition_id' => 'ESPCUP',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        $winner = Team::factory()->create(['country' => 'ES']);
        $loser = Team::factory()->create(['country' => 'ES']);

        $tie = CupTie::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESPCUP',
            'home_team_id' => $winner->id,
            'away_team_id' => $loser->id,
            'completed' => true,
            'winner_id' => $winner->id,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESPCUP',
            'home_team_id' => $winner->id,
            'away_team_id' => $loser->id,
        ]);

        $explodingDraw = new class extends CupDrawService {
            public function __construct() {}

            public function getNextRoundNeedingDraw(string $gameId, string $competitionId): ?int
            {
                return 2;
            }

            public function conductDraw(
                string $gameId,
                string $competitionId,
                int $roundNumber,
                ?array $explicitPairings = null,
            ): Collection {
                throw new \RuntimeException('unexpected draw failure');
            }
        };

        $event = new CupTieResolved(
            cupTie: $tie,
            winnerId: (string) $winner->id,
            match: $match,
            game: $game,
            competition: Competition::find('ESPCUP'),
        );

        // No debe lanzar: la excepción queda registrada y la copa se abandona.
        (new ConductNextCupRoundDraw($explodingDraw))->handle($event);

        $this->assertTrue(true);
    }
}

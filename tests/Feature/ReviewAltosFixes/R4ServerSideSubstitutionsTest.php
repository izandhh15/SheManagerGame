<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\MatchEvent;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Match\Enums\MatchPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R4 [ALTA]: `previousSubstitutions` llegaba del cliente y los límites de
 * 5 cambios / 3 ventanas se contaban sobre ese valor — con
 * `previousSubstitutions: []` forjado se burlaban los límites (familia A1).
 *
 * El fix deriva el historial en servidor desde los eventos persistidos del
 * partido (`SubstitutionService::serverSubstitutionHistory()`) e ignora el
 * valor del cliente tanto en `validateBatchSubstitution()` como en
 * `TacticalChangeService::processLiveMatchChanges()`.
 */
class R4ServerSideSubstitutionsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReviewScenario;

    private function persistSubstitution(string $gameId, string $matchId, string $teamId, string $outId, string $inId, int $minute): void
    {
        // Same shape processLiveMatchChanges() writes for accepted batches.
        MatchEvent::create([
            'game_id' => $gameId,
            'game_match_id' => $matchId,
            'game_player_id' => $outId,
            'team_id' => $teamId,
            'minute' => $minute,
            'phase' => MatchPhase::SECOND_HALF->value,
            'stoppage_minute' => null,
            'event_type' => MatchEvent::TYPE_SUBSTITUTION,
            'metadata' => ['player_in_id' => $inId],
        ]);
    }

    public function test_forged_empty_history_does_not_bypass_five_sub_limit(): void
    {
        [$game, $team, , $match, $homeLineup, , $homeBench] = $this->buildReviewScenario();

        // 5 cambios ya aceptados y persistidos en el servidor.
        for ($i = 0; $i < 5; $i++) {
            $this->persistSubstitution(
                $game->id, $match->id, $team->id,
                $homeLineup[$i]->id, $homeBench[$i]->id, 60 + $i,
            );
        }

        $service = app(SubstitutionService::class);

        // El servidor ve los 5 aunque el cliente jure que no hay ninguno.
        $this->assertCount(5, $service->serverSubstitutionHistory($match, $game));

        // El 6.º cambio con previousSubstitutions: [] forjado debe rebotar.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('game.sub_error_limit_reached');

        $service->validateBatchSubstitution(
            $match,
            $game,
            [['playerOutId' => $homeLineup[5]->id, 'playerInId' => $homeBench[5]->id]],
            70,
            [], // <-- historial forjado por el cliente
        );
    }

    public function test_forged_empty_history_does_not_bypass_window_limit(): void
    {
        [$game, $team, , $match, $homeLineup, , $homeBench] = $this->buildReviewScenario();

        // 3 ventanas reales ya consumidas (minutos distintos, ninguno libre).
        for ($i = 0; $i < 3; $i++) {
            $this->persistSubstitution(
                $game->id, $match->id, $team->id,
                $homeLineup[$i]->id, $homeBench[$i]->id, 60 + $i,
            );
        }

        $service = app(SubstitutionService::class);

        // Abrir una 4.ª ventana con previousSubstitutions: [] forjado debe rebotar.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('game.sub_error_windows_reached');

        $service->validateBatchSubstitution(
            $match,
            $game,
            [['playerOutId' => $homeLineup[3]->id, 'playerInId' => $homeBench[3]->id]],
            70,
            [], // <-- historial forjado por el cliente
        );
    }

    public function test_client_history_is_ignored_in_both_directions(): void
    {
        [$game, , , $match, $homeLineup, , $homeBench] = $this->buildReviewScenario();

        $service = app(SubstitutionService::class);

        // Sin cambios reales, el cliente no puede inventar 5 previos para
        // bloquear un cambio legal: el valor del cliente se ignora del todo.
        $forged = [];
        for ($i = 0; $i < 5; $i++) {
            $forged[] = [
                'playerOutId' => $homeLineup[$i]->id,
                'playerInId' => $homeBench[$i]->id,
                'minute' => 60 + $i,
            ];
        }

        // No debe lanzar: el historial real está vacío.
        $service->validateBatchSubstitution(
            $match,
            $game,
            [['playerOutId' => $homeLineup[0]->id, 'playerInId' => $homeBench[0]->id]],
            65,
            $forged,
        );

        $this->assertTrue(true);
    }

    public function test_legal_batch_still_passes_with_forged_empty_history(): void
    {
        [$game, $team, , $match, $homeLineup, , $homeBench] = $this->buildReviewScenario();

        // 2 cambios reales: el 3.º sigue siendo legal aunque el cliente
        // mande [].
        for ($i = 0; $i < 2; $i++) {
            $this->persistSubstitution(
                $game->id, $match->id, $team->id,
                $homeLineup[$i]->id, $homeBench[$i]->id, 60 + $i,
            );
        }

        $service = app(SubstitutionService::class);

        $service->validateBatchSubstitution(
            $match,
            $game,
            [['playerOutId' => $homeLineup[2]->id, 'playerInId' => $homeBench[2]->id]],
            70,
            [],
        );

        $this->assertTrue(true);
    }
}

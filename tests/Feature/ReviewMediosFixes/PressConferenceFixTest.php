<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\PreMatchPress;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\PressConferenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 5 de la revisión de medios: PressConferenceService::answer() hacía
 * check-then-create sin lock (doble submit concurrente → 500 por la unique)
 * y si applyEffects() fallaba tras el create, el reintento devolvía el
 * registro sin aplicar los efectos nunca.
 *
 * Ahora la secuencia corre en transacción serializada sobre la fila del
 * juego y el flag effects_applied permite a un reintento completar unos
 * efectos que quedaron pendientes, exactamente una vez.
 */
class PressConferenceFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_answer_is_idempotent_and_applies_effects_once(): void
    {
        [$game, , $match] = $this->scenario();
        $service = app(PressConferenceService::class);
        $answers = $this->validAnswers($service, $game, $match);

        $before = (int) $game->board_confidence;

        $first = $service->answer($game->refresh(), $match, $answers);
        $second = $service->answer($game->refresh(), $match, $answers);

        $this->assertSame($first->id, $second->id, 'La segunda llamada debe devolver el mismo registro.');
        $this->assertSame(
            1,
            PreMatchPress::where('game_id', $game->id)->where('match_id', $match->id)->count(),
            'Solo debe existir un registro por partido.'
        );
        $this->assertTrue($second->refresh()->effects_applied);

        // Los efectos (p. ej. 'all_in' → confidence +2) se aplican una sola vez.
        $this->assertSame(
            $before + $first->confidence_delta,
            (int) $game->refresh()->board_confidence,
            'La segunda llamada no debe reaplicar los efectos.'
        );
    }

    public function test_retry_completes_effects_left_pending_by_a_crashed_attempt(): void
    {
        [$game, , $match] = $this->scenario();
        $service = app(PressConferenceService::class);
        $answers = $this->validAnswers($service, $game, $match);

        // Simula el crash entre el insert y applyEffects(): la fila existe
        // pero los efectos nunca se aplicaron.
        $crashed = PreMatchPress::create([
            'game_id' => $game->id,
            'match_id' => $match->id,
            'answers' => $answers,
            'morale_delta' => 4,
            'confidence_delta' => 2,
            'effects_applied' => false,
        ]);

        $before = (int) $game->board_confidence;
        $record = $service->answer($game->refresh(), $match, $answers);

        $this->assertSame($crashed->id, $record->id);
        $this->assertTrue($record->refresh()->effects_applied, 'El reintento debe marcar los efectos como aplicados.');
        $this->assertSame(
            $before + 2,
            (int) $game->refresh()->board_confidence,
            'El reintento debe aplicar los efectos pendientes.'
        );
    }

    public function test_invalid_answer_still_throws_before_any_write(): void
    {
        [$game, , $match] = $this->scenario();
        $service = app(PressConferenceService::class);

        try {
            $service->answer($game, $match, ['importance' => 'no_such_answer']);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (\InvalidArgumentException) {
            // Esperado: la validación ocurre antes del lock y de escribir.
        }

        $this->assertSame(
            0,
            PreMatchPress::where('game_id', $game->id)->count(),
            'Una respuesta inválida no debe dejar rastro.'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{Game, Team, GameMatch} */
    private function scenario(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $rival = Team::factory()->create(['name' => 'Rival WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
            'board_confidence' => 70,
        ]);
        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $rival->id,
        ]);

        return [$game, $team, $match];
    }

    /** Respuestas válidas construidas desde las propias preguntas del servicio. */
    private function validAnswers(PressConferenceService $service, Game $game, GameMatch $match): array
    {
        $answers = [];
        foreach ($service->questions($game, $match) as $q) {
            $answers[$q['key']] = $q['answers'][0]['key'];
        }

        return $answers;
    }
}

<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\MatchEvent;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R10 [ALTA]: `processExtraTime()` no tenía guard de idempotencia — un
 * segundo POST (doble clic) re-simulaba la prórroga, sobrescribía
 * `home_score_et`/`away_score_et` y DUPLICABA los eventos de ET vía
 * `bulkInsert` (goles fantasma). La acción gemela de penaltis sí tiene
 * guard (`home_score_penalties !== null` → 400).
 *
 * El fix hace early-return en el servicio si `is_extra_time` ya es true,
 * reconstruyendo el resultado desde el estado persistido.
 */
class R10ExtraTimeIdempotentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReviewScenario;

    private function extraTimeEventCount(string $matchId): int
    {
        return MatchEvent::where('game_match_id', $matchId)
            ->get()
            ->filter(fn (MatchEvent $e) => $e->phase->isExtraTime())
            ->count();
    }

    public function test_second_call_does_not_resimulate_or_duplicate_events(): void
    {
        [$game, , , $match] = $this->buildReviewScenario();

        $service = app(ExtraTimeAndPenaltyService::class);

        $first = $service->processExtraTime($match->refresh(), $game);
        $eventsAfterFirst = $this->extraTimeEventCount($match->id);

        $second = $service->processExtraTime($match->refresh(), $game);
        $eventsAfterSecond = $this->extraTimeEventCount($match->id);

        $this->assertSame(
            $eventsAfterFirst,
            $eventsAfterSecond,
            'La segunda llamada a processExtraTime() no debe persistir más eventos de prórroga.'
        );

        $this->assertSame($first->homeScoreET, $second->homeScoreET);
        $this->assertSame($first->awayScoreET, $second->awayScoreET);
        $this->assertSame($eventsAfterFirst, $second->storedEvents->count());
        $this->assertSame($first->needsPenalties, $second->needsPenalties);

        // El marcador de ET del partido no se toca en la segunda llamada.
        $this->assertSame($first->homeScoreET, (int) $match->refresh()->home_score_et);
    }
}

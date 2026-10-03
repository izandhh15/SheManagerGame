<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\AITacticsService;
use Tests\TestCase;

/**
 * Fix 8 de la revisión de medios: la rama IA de LineupService con
 * $allPlayersGrouped null dejaba opponentAvg=0, con lo que
 * $diff = teamAvg - 0 >= 5 hacía isStronger=true SIEMPRE (equipos IA débiles
 * jugando posesión/presión alta). El fallback ahora asume rival más fuerte
 * (opponentAvg = teamAvg + 5) y el planteamiento es conservador, como el
 * path con datos cuando el rival es genuinamente superior.
 */
class LineupAiFallbackFixTest extends TestCase
{
    public function test_unknown_opponent_yields_conservative_instructions(): void
    {
        $service = app(AITacticsService::class);
        $teamAvg = 75;

        // El fallback que LineupService usa sin datos del rival.
        $opponentAvg = $teamAvg + 5;

        [$style, $pressing, $defLine] = $service->selectAIInstructions(
            'modest', false, $teamAvg, $opponentAvg
        );

        $this->assertSame(PlayingStyle::COUNTER_ATTACK, $style);
        $this->assertSame(PressingIntensity::LOW_BLOCK, $pressing);
        $this->assertSame(DefensiveLineHeight::DEEP, $defLine);
    }

    public function test_unknown_opponent_yields_defensive_mentality_away(): void
    {
        $service = app(AITacticsService::class);

        $mentality = $service->selectAIMentality('modest', false, 75, 80);

        $this->assertSame(Mentality::DEFENSIVE, $mentality);
    }

    public function test_zero_opponent_avg_no_longer_means_stronger(): void
    {
        // Regresión del bug: con opponentAvg=0 el equipo siempre parecía
        // más fuerte ($diff >= 5). El nuevo fallback (teamAvg+5) invierte
        // la comparación y el equipo se trata como débil.
        $service = app(AITacticsService::class);

        [$style] = $service->selectAIInstructions('elite', true, 75, 0);
        $this->assertSame(
            PlayingStyle::POSSESSION,
            $style,
            'Con opponentAvg=0 el equipo elite en casa juega posesión (comportamiento antiguo del bug).'
        );

        [$styleFixed] = $service->selectAIInstructions('elite', true, 75, 80);
        $this->assertSame(
            PlayingStyle::COUNTER_ATTACK,
            $styleFixed,
            'Con el fallback (rival más fuerte) el mismo equipo se repliega.'
        );
    }
}

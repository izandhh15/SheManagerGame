<?php

namespace Tests\Feature\QaMediumFixes;

use App\Modules\Match\Services\MatchdayOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M26 (QA agent-24).
 *
 * `MatchdayOrchestrator::checkLeagueWithPlayoffSeasonEnd()` notificaba
 * `cup.direct_promotion` con el umbral hardcodeado `position <= 2` y
 * `cup.promotion_playoff` con `position <= 6` para TODAS las ligas con
 * playoff. En ESP2 (direct_count = 1, playoff_count = 4 según la regla
 * ESP1↔ESP2 de config/countries.php) el 2º recibía "ascenso directo"
 * cuando en realidad va al playoff, y el 6º recibía "playoff" cuando
 * queda fuera. Ahora los umbrales se derivan del generador de playoff
 * de cada competición (vía PlayoffGeneratorFactory), igual que ya hacía
 * LeaguePlayoffProgressResolver para la UI.
 */
class M26PlayoffPromotionThresholdsTest extends TestCase
{
    use RefreshDatabase;

    private MatchdayOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orchestrator = $this->app->make(MatchdayOrchestrator::class);
    }

    public function test_esp2_champion_gets_direct_promotion_label(): void
    {
        $this->assertSame(
            'cup.direct_promotion',
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ESP2', 1)
        );
    }

    public function test_esp2_second_place_gets_playoff_label_not_direct(): void
    {
        // Con los umbrales viejos (<= 2) el 2º de ESP2 recibía
        // "ascenso directo" por error; solo el 1º sube directo.
        $this->assertSame(
            'cup.promotion_playoff',
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ESP2', 2)
        );
    }

    public function test_esp2_playoff_bracket_positions_get_playoff_label(): void
    {
        // ESP2: direct_count = 1, playoff_count = 4 → playoff en [2..5].
        foreach ([3, 4, 5] as $position) {
            $this->assertSame(
                'cup.promotion_playoff',
                $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ESP2', $position),
                "position {$position}"
            );
        }
    }

    public function test_esp2_sixth_place_gets_no_notification(): void
    {
        // Con los umbrales viejos (<= 6) el 6º recibía "playoff" por error.
        $this->assertNull(
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ESP2', 6)
        );
    }

    public function test_wsl_labels_are_perspective_aware(): void
    {
        // ENG2 (WSL2): la campeona sube directa, la 2ª va al playoff.
        $this->assertSame(
            'cup.direct_promotion',
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ENG2', 1)
        );
        $this->assertSame(
            'cup.promotion_playoff',
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ENG2', 2)
        );

        // ENG1 (WSL): la 13ª juega el playoff de descenso; el 2º de ENG1
        // no "asciende" a ningún sitio (con los umbrales viejos recibía
        // "ascenso directo").
        $this->assertSame(
            'cup.relegation_playoff',
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ENG1', 13)
        );
        $this->assertNull(
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ENG1', 2)
        );
    }

    public function test_competition_without_playoff_generator_gets_no_notification(): void
    {
        $this->assertNull(
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('ESP1', 1)
        );
        $this->assertNull(
            $this->orchestrator->resolveLeaguePlayoffSeasonEndLabel('NOPE', 1)
        );
    }
}

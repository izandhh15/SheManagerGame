<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Modules\Match\Support\PaperStrength;
use Tests\TestCase;

/**
 * Fix 13 de la revisión de medios: PaperStrength dividía por 11 fijo, así
 * que una alineación corta (7-10, permitida por MIN_LINEUP_SIZE) veía su
 * fuerza infravalorada hasta un 36%. Ahora divide por las jugadoras reales.
 */
class PaperStrengthFixTest extends TestCase
{
    public function test_full_xi_strength_is_unchanged(): void
    {
        $players = $this->players(11, 80, 70);

        // (80*0.95 + 70*0.05) / 100 = 0.795
        $this->assertEqualsWithDelta(0.795, PaperStrength::estimate($players), 0.0001);
    }

    public function test_short_lineup_is_not_undervalued(): void
    {
        // Misma calidad media que el once completo: la fuerza debe ser la
        // misma, no 7/11 de ella.
        $full = PaperStrength::estimate($this->players(11, 80, 70));
        $short = PaperStrength::estimate($this->players(7, 80, 70));

        $this->assertEqualsWithDelta($full, $short, 0.0001);
    }

    public function test_seven_player_lineup_keeps_full_average(): void
    {
        $players = $this->players(7, 90, 80);

        // (90*0.95 + 80*0.05) / 100 = 0.895 (con el bug: 0.569)
        $this->assertEqualsWithDelta(0.895, PaperStrength::estimate($players), 0.0001);
    }

    /** @return object[] */
    private function players(int $count, int $overall, int $morale): array
    {
        $players = [];
        for ($i = 0; $i < $count; $i++) {
            $players[] = (object) ['overall_score' => $overall, 'morale' => $morale];
        }

        return $players;
    }
}

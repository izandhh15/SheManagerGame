<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Modules\Season\Services\ClubFormService;
use Tests\TestCase;

/**
 * Fix 15 de la revisión de medios: $seed se capturaba por valor en el
 * closure $rand, así que todas las llamadas con el mismo rango devolvían
 * el mismo valor — el jitter de goles y el de asistencias ($rand(0,2) dos
 * veces) eran idénticos y las "variaciones" colapsaban. Ahora la semilla
 * avanza entre llamadas.
 */
class ClubFormServiceFixTest extends TestCase
{
    public function test_goals_and_assists_jitters_are_independent_draws(): void
    {
        // Para una portera de overall 90 (quality = 1.0, per90 g = 0.0):
        //   goals   = round(J1)            con J1 = primer  rand(0,2)
        //   assists = round(C + J2) = J2    con J2 = segundo rand(0,2)
        // (C = nineties*0.03 < 0.16, nunca cruza el .5 del redondeo).
        // Con la semilla capturada por valor J1 === J2 SIEMPRE → goals ===
        // assists para las 300 porteras. Con la semilla avanzando, difieren
        // ~2/3 de las veces.
        $different = 0;
        $total = 300;
        for ($i = 0; $i < $total; $i++) {
            $stats = ClubFormService::statsFor("keeper-$i", 90, 'Goalkeeper', '2026', 1.0);
            if ($stats['goals'] !== $stats['assists']) {
                $different++;
            }
        }

        $this->assertGreaterThan(
            100,
            $different,
            "goals y assists usan el mismo draw ({$different}/{$total} difieren): la semilla no avanza entre llamadas"
        );
    }

    public function test_stats_are_still_deterministic(): void
    {
        $first = ClubFormService::statsFor('player-x', 82, 'Midfielder', '2026', 1.0);
        $second = ClubFormService::statsFor('player-x', 82, 'Midfielder', '2026', 1.0);

        $this->assertSame($first, $second);
    }

    public function test_stats_vary_between_players_and_seasons(): void
    {
        $a = ClubFormService::statsFor('player-a', 80, 'Forward', '2026', 1.0);
        $b = ClubFormService::statsFor('player-b', 80, 'Forward', '2026', 1.0);
        $c = ClubFormService::statsFor('player-a', 80, 'Forward', '2027', 1.0);

        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function test_stats_never_negative(): void
    {
        foreach ([40, 60, 80, 95] as $overall) {
            foreach (['Goalkeeper', 'Defender', 'Midfielder', 'Forward'] as $group) {
                $stats = ClubFormService::statsFor("p-$overall-$group", $overall, $group, '2026', 1.0);
                foreach (['appearances', 'minutes', 'goals', 'assists', 'clean_sheets'] as $key) {
                    $this->assertGreaterThanOrEqual(0, $stats[$key], "$key negativo para $overall/$group");
                }
            }
        }
    }
}

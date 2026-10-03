<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Modules\Match\Services\AIMatchResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R14 [ALTA]: `AIMatchResolver::generateCardEvents()` sorteaba la roja
 * directa del once completo con minuto independiente, sin excluir a las ya
 * amonestadas — una jugadora podía ver amarilla en el 70' y roja directa en
 * el 20' (patrón exacto del bug A18 del QA; el fix del QA no había llegado
 * a esta rama).
 *
 * El fix excluye `$cardedPlayerIds` del sorteo de la roja directa.
 * Además, el ruido de posesión `(crc32($match->id) % 7) - 3` no estaba
 * saneado (familia C6: en PHP 32-bit sale negativo ~50% de las veces y el
 * ruido se salía del rango ±3 documentado): ahora lleva `& 0x7FFFFFFF`.
 */
class R14NoYellowAfterRedTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReviewScenario;

    /**
     * Invoca el generador privado de tarjetas y devuelve los eventos.
     */
    private function generateCardEvents(AIMatchResolver $resolver, $lineup, string $teamId): array
    {
        $method = new \ReflectionMethod(AIMatchResolver::class, 'generateCardEvents');
        $method->setAccessible(true);

        $events = [];
        $method->invokeArgs($resolver, [&$events, $lineup, $teamId]);

        return $events;
    }

    public function test_no_player_gets_yellow_and_direct_red_in_same_match(): void
    {
        [$game, $team, , , $homeLineup] = $this->buildReviewScenario();

        $resolver = app(AIMatchResolver::class);

        // Suficientes iteraciones para que, sin el fix, una amonestada caiga
        // en el sorteo de la roja directa (p ~= 0.4% por equipo y partido).
        for ($i = 0; $i < 400; $i++) {
            $events = $this->generateCardEvents($resolver, $homeLineup, $team->id);

            $yellowed = [];
            $redded = [];
            foreach ($events as $event) {
                if ($event['event_type'] === 'yellow_card') {
                    $yellowed[$event['game_player_id']] = true;
                }
                if ($event['event_type'] === 'red_card') {
                    $redded[$event['game_player_id']] = true;
                }
            }

            $both = array_intersect_key($yellowed, $redded);
            $this->assertEmpty(
                $both,
                'Una jugadora no puede ver amarilla y roja directa en el mismo partido.'
            );
        }
    }

    public function test_possession_noise_stays_within_documented_range_on_32bit(): void
    {
        // El fix es `((crc32($match->id) & 0x7FFFFFFF) % 7) - 3`: el ruido
        // debe quedar en [-3, 3] tanto en 64-bit como emulando 32-bit, y el
        // saneamiento no debe cambiar el valor en 64-bit.
        $source = file_get_contents(
            (new \ReflectionClass(AIMatchResolver::class))->getFileName()
        );
        $this->assertStringContainsString(
            '(crc32($match->id) & 0x7FFFFFFF) % 7',
            $source,
            'El ruido de posesión debe sanear crc32() con & 0x7FFFFFFF (familia C6).'
        );

        $buggyNoiseCount = 0;
        for ($i = 0; $i < 200; $i++) {
            $id = 'test-match-' . $i . '-' . uniqid();
            $unsigned = crc32($id);
            $signed = $this->signed32($unsigned);

            $noise64 = (($unsigned & 0x7FFFFFFF) % 7) - 3;
            $noise32 = (($signed & 0x7FFFFFFF) % 7) - 3;

            $this->assertSame($noise64, $noise32, 'El ruido debe coincidir en 32-bit y 64-bit.');
            $this->assertGreaterThanOrEqual(-3, $noise64);
            $this->assertLessThanOrEqual(3, $noise64);

            // Sin el fix, en PHP 32-bit el ruido saldría del rango ±3 para
            // las semillas con crc32 negativo no múltiplo de 7.
            if ($signed < 0 && $signed % 7 !== 0) {
                $buggyNoiseCount++;
            }
        }

        $this->assertGreaterThan(
            0,
            $buggyNoiseCount,
            'El test debe cubrir semillas donde el bug 32-bit se manifestaría.'
        );
    }
}

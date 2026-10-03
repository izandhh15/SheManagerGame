<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Modules\Stadium\Services\MatchAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R19 [ALTA] (familia C6): `MatchAttendanceService` calculaba
 * `$bucket = crc32($match->id) % 601` sin máscara. En PHP 32-bit (Wasmer
 * Edge, producción) crc32() devuelve negativo ~50% de las veces y el bucket
 * salía negativo → el jitter de taquilla (−1%..+5% de la capacidad) se
 * corrompía en la mitad de los partidos.
 *
 * El fix es `(crc32($match->id) & 0x7FFFFFFF) % 601`, que deja el bucket en
 * [0, 600] en todas las plataformas. En local (64-bit) crc32() nunca es
 * negativo, así que el test emula la semántica de 32-bit con signed32()
 * (técnica portada de los tests C6 del QA).
 */
class R19AttendanceBucketTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReviewScenario;

    public function test_bucket_is_masked_in_source(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(MatchAttendanceService::class))->getFileName()
        );

        $this->assertStringContainsString(
            '(crc32($match->id) & 0x7FFFFFFF) % 601',
            $source,
            'El bucket de asistencia debe sanear crc32() con & 0x7FFFFFFF (familia C6).'
        );
    }

    public function test_bucket_stays_in_range_when_crc32_would_be_negative_on_32bit(): void
    {
        $checkedNegative = 0;

        for ($i = 0; $i < 500; $i++) {
            // IDs con pinta de UUID de partido.
            $id = sprintf('%08x-%04x-4%03x-%04x-%012x', $i * 7919, $i, $i * 13, $i * 7, $i * 104729);
            $unsigned = crc32($id);
            $signed = $this->signed32($unsigned);

            // La expresión fixeada, evaluada con semántica 32-bit y 64-bit.
            $bucket32 = ($signed & 0x7FFFFFFF) % 601;
            $bucket64 = ($unsigned & 0x7FFFFFFF) % 601;

            $this->assertSame($bucket64, $bucket32, 'El bucket debe coincidir en 32-bit y 64-bit.');
            $this->assertGreaterThanOrEqual(0, $bucket32);
            $this->assertLessThanOrEqual(600, $bucket32);

            if ($signed < 0 && $signed % 601 !== 0) {
                $checkedNegative++;
                // Precondición: sin el fix, este ID habría dado bucket
                // negativo en producción (PHP 32-bit).
                $this->assertLessThan(
                    0,
                    $signed % 601,
                    "Sin el fix, el bucket para {$id} sería negativo en 32-bit."
                );
            }
        }

        $this->assertGreaterThan(
            0,
            $checkedNegative,
            'El test debe cubrir IDs cuyo crc32 sería negativo en PHP 32-bit.'
        );
    }
}

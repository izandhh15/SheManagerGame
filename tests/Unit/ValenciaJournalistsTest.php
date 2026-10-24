<?php

namespace Tests\Unit;

use App\Modules\Media\Services\JournalistService;
use Tests\TestCase;

/**
 * Beat del València CF Femení (2026-10-24, nombres y medios dados
 * por Izan Delgado). Solo comprueba constantes: no toca la BD, así
 * que puede correrse sin Postgres. Los handles son identificadores
 * dentro del juego, no handles reales afirmados en X.
 */
class ValenciaJournalistsTest extends TestCase
{
    public function test_valencia_beat_is_in_the_roster(): void
    {
        $names = array_column(JournalistService::ROSTER, 0);
        $handles = array_column(JournalistService::ROSTER, 1);

        foreach (JournalistService::CLUB_JOURNALISTS['València CF Femení'] as $name) {
            $this->assertContains($name, $names, "Falta en ROSTER: {$name}");
            $this->assertArrayHasKey($name, JournalistService::OUTLETS);
            $this->assertArrayHasKey($name, JournalistService::SOURCES);
            $this->assertNotEmpty(JournalistService::SOURCES[$name]);
        }

        $this->assertSame('EFE / À Punt / Mundo Deportivo', JournalistService::OUTLETS['Paula Lerín']);
        $this->assertSame('Marca', JournalistService::OUTLETS['Luco Cortés']);
        $this->assertSame('Las del Dato', JournalistService::OUTLETS['Bea Beltrán']);
        $this->assertSame('Las del Dato', JournalistService::OUTLETS['Inés Miró']);
        $this->assertSame('El Nostre Equip', JournalistService::OUTLETS['Izan Delgado']);
        $this->assertSame('El Nostre Equip', JournalistService::OUTLETS['Lola Pulido']);

        // Handles únicos y con pinta de cuenta personal, no de marca.
        $this->assertSame(count($handles), count(array_unique($handles)));
        foreach (['@paulalerin', '@lucocortes', '@beabeltran', '@inesmiro', '@izandelgado', '@lolapulido'] as $handle) {
            $this->assertContains($handle, $handles);
        }

        // Los titulares por especialidad no cambian: los del València
        // van al final del roster a propósito.
        $this->assertSame('David Menayo', JournalistService::ROSTER[0][0]);
        $this->assertSame('Sandra Riquelme', JournalistService::ROSTER[1][0]);
    }
}

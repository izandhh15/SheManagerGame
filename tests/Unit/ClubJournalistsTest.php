<?php

namespace Tests\Unit;

use App\Modules\Media\Services\JournalistService;
use Tests\TestCase;

/**
 * Beats por club, investigación ampliada 2026-10-24. Solo constantes:
 * no toca la BD, así que puede correrse sin Postgres. Los handles son
 * identificadores dentro del juego, no handles reales afirmados en X.
 * Varios son bylines de un artículo citado, no un beat permanente
 * confirmado: eso queda documentado en JournalistService::SOURCES.
 */
class ClubJournalistsTest extends TestCase
{
    public function test_new_club_beats_are_in_the_roster(): void
    {
        $names = array_column(JournalistService::ROSTER, 0);
        $handles = array_column(JournalistService::ROSTER, 1);

        $expected = [
            'Laia Bonals' => 'Ara.cat',
            'Sergio Quintana' => 'El Correo',
            'Aimara G. Gil' => 'Diario AS',
            'Maite Caridad' => 'Marca',
            'Eric Martín' => 'Las Provincias',
            'Hugo Guillemet' => "L'Équipe",
            'Kathryn Batte' => 'Daily Mail',
            'Eskender Tamrat' => 'BuliNews',
        ];

        foreach ($expected as $name => $outlet) {
            $this->assertContains($name, $names, "Falta en ROSTER: {$name}");
            $this->assertSame($outlet, JournalistService::OUTLETS[$name]);
            $this->assertNotEmpty(JournalistService::SOURCES[$name]);
        }

        $this->assertSame(count($handles), count(array_unique($handles)));
        $this->assertContains('David Menayo', JournalistService::CLUB_JOURNALISTS['Real Madrid']);
        $this->assertContains('Laia Bonals', JournalistService::CLUB_JOURNALISTS['FC Barcelona']);
        $this->assertContains('Sergio Quintana', JournalistService::CLUB_JOURNALISTS['Athletic Club']);
        $this->assertContains('Eskender Tamrat', JournalistService::CLUB_JOURNALISTS['VfL Wolfsburg']);

        // Decisiones deliberadas de NO añadir: Eibar solo por iniciales
        // (J. A. Rementería, nombre completo sin confirmar) y los otros
        // dos bylines del Athletic de un solo artículo.
        $this->assertNotContains('J. A. Rementería', $names);
        $this->assertNotContains('Juan Carlos Grande', $names);
        $this->assertNotContains('Carlos Nieto', $names);

        // Los titulares por especialidad no cambian.
        $this->assertSame('David Menayo', JournalistService::ROSTER[0][0]);
        $this->assertSame('Sandra Riquelme', JournalistService::ROSTER[1][0]);
    }
}

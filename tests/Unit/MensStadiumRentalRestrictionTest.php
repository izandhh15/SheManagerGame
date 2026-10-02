<?php

namespace Tests\Unit;

use App\Modules\Stadium\Services\MensStadiumRequestService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Restricted men's-stadium rental (02-10-2026): each women's club can
 * ONLY rent its own men's team's ground ("la casa del equipo masculino").
 * Clubs without a mapped men's team keep the old behaviour (full
 * catalogue, no restrictions).
 *
 * Pure data-driven service: no database needed.
 */
class MensStadiumRentalRestrictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The service caches the raw JSON for 24h; drop it so every test
        // reads the current data/mens_stadiums.json from the repo.
        Cache::forget('mens_stadiums_v2');
    }

    private function service(): MensStadiumRequestService
    {
        return new MensStadiumRequestService;
    }

    public function test_valencia_only_rents_mestalla(): void
    {
        $service = $this->service();

        $catalogue = $service->rentalCatalogue('Valencia CF Femenino');

        $this->assertCount(1, $catalogue);
        $this->assertSame('Mestalla', $catalogue[0]['stadium']);
        $this->assertSame('Mestalla|Valencia CF', $catalogue[0]['key']);

        $this->assertTrue($service->hasMensStadium('Valencia CF Femenino'));

        $this->assertTrue($service->isRentableBy('Valencia CF Femenino', 'Mestalla|Valencia CF'));
        $this->assertFalse($service->isRentableBy('Valencia CF Femenino', 'Camp Nou|FC Barcelona'));
        $this->assertFalse($service->isRentableBy('Valencia CF Femenino', 'La Cartuja|Ayuntamiento de Sevilla'));
    }

    public function test_unmapped_club_keeps_full_catalogue(): void
    {
        $service = $this->service();

        // Madrid CFF is a women's-only club: no men's ground mapped.
        $this->assertFalse($service->hasMensStadium('Madrid CFF'));
        $this->assertNull($service->mensStadiumFor('Madrid CFF'));

        $catalogue = $service->rentalCatalogue('Madrid CFF');

        // Full catalogue: more than a hundred grounds, same as any other
        // unmapped club (e.g. Logroño United).
        $this->assertGreaterThan(100, count($catalogue));
        $this->assertCount(count($catalogue), $service->rentalCatalogue('Logroño United'));

        // No restrictions at all: anything is rentable.
        $this->assertTrue($service->isRentableBy('Madrid CFF', 'Mestalla|Valencia CF'));
        $this->assertTrue($service->isRentableBy('Madrid CFF', 'Camp Nou|FC Barcelona'));
        $this->assertTrue($service->isRentableBy('Madrid CFF', 'La Cartuja|Ayuntamiento de Sevilla'));
    }

    public function test_first_team_and_b_team_share_the_mens_ground(): void
    {
        $service = $this->service();

        // The women's first team (ESP2) was missing; it now maps alongside
        // the B team (ESP3B) to the same ground.
        foreach (['Levante UD', 'Levante UD B'] as $team) {
            $catalogue = $service->rentalCatalogue($team);
            $this->assertCount(1, $catalogue, $team);
            $this->assertSame('Ciutat de València', $catalogue[0]['stadium'], $team);
        }

        $this->assertSame('El Sadar', $service->rentalCatalogue('CA Osasuna')[0]['stadium']);
        $this->assertSame('El Sadar', $service->rentalCatalogue('CA Osasuna B')[0]['stadium']);

        // B teams get the affiliated "precio de la casa" too.
        $osasuna = $service->stadiumByKey('El Sadar|CA Osasuna');
        $this->assertTrue($service->isAffiliated($osasuna, 'CA Osasuna'));
        $this->assertTrue($service->isAffiliated($osasuna, 'CA Osasuna B'));
        $this->assertFalse($service->isAffiliated($osasuna, 'Real Madrid CF'));
    }

    public function test_new_mappings_point_to_real_grounds(): void
    {
        $service = $this->service();

        $expectations = [
            'FC Badalona Women' => 'Nou Estadi de Palamós',
            'Burgos CF' => 'El Plantío',
            'Málaga CF' => 'La Rosaleda',
            'CD Getafe Femenino' => 'Coliseum Alfonso Pérez',
            'SD Huesca' => 'El Alcoraz',
            'Granada CF B' => 'Nuevo Los Cármenes',
            'Parma Calcio 1913' => 'Ennio Tardini',
            'Hellas Verona' => 'Marcantonio Bentegodi',
            'Genoa CFC' => 'Luigi Ferraris',
            'Brighton & Hove Albion WFC' => 'Amex Stadium',
            'Crystal Palace LFC' => 'Selhurst Park',
            'Burnley' => 'Turf Moor',
            'Leicester City' => 'King Power Stadium',
            'Newcastle United' => "St James' Park",
            'Sheffield United' => 'Bramall Lane',
            'Sunderland' => 'Stadium of Light',
            'Wolverhampton Wanderers' => 'Molineux',
            'Borussia Mönchengladbach' => 'Borussia-Park',
            'VfL Bochum' => 'Vonovia Ruhrstadion',
            'Hertha BSC' => 'Olympiastadion Berlin',
            'TSG 1899 Hoffenheim' => 'PreZero Arena',
            'TSG 1899 Hoffenheim II' => 'PreZero Arena',
            '1. FSV Mainz 05' => 'Mewa Arena',
            'LOSC Lille' => 'Pierre-Mauroy',
            'OGC Nice' => 'Allianz Riviera',
            'Montpellier FC' => 'Stade de la Mosson',
            'Toulouse FC' => 'Stadium de Toulouse',
            'FC Nantes' => 'Stade de la Beaujoire',
            'CD Tenerife Femenino B' => 'Heliodoro Rodríguez López',
        ];

        foreach ($expectations as $team => $stadium) {
            $catalogue = $service->rentalCatalogue($team);
            $this->assertCount(1, $catalogue, $team);
            $this->assertSame($stadium, $catalogue[0]['stadium'], $team);
        }
    }

    public function test_liga_f_is_fully_covered_or_unmapped_by_design(): void
    {
        $service = $this->service();

        // Every Liga F club with a men's team maps to its ground.
        $mapped = [
            'FC Barcelona' => 'Camp Nou',
            'Real Madrid CF' => 'Santiago Bernabéu',
            'Atlético de Madrid' => 'Riyadh Air Metropolitano',
            'Athletic Club' => 'San Mamés',
            'Real Sociedad' => 'Reale Arena',
            'Deportivo Alavés' => 'Mendizorrotza',
            'Sevilla FC' => 'Ramón Sánchez-Pizjuán',
            'Real Betis Balompié' => 'Benito Villamarín',
            'Villarreal CF' => 'Estadio de la Cerámica',
            'Valencia CF Femenino' => 'Mestalla',
            'Rayo Vallecano' => 'Vallecas',
            'RCD Espanyol' => 'RCDE Stadium',
            'SD Eibar' => 'Ipurua',
            'Granada CF' => 'Nuevo Los Cármenes',
            'Elche CF' => 'Manuel Martínez Valero',
            'Costa Adeje Tenerife Egatesa' => 'Heliodoro Rodríguez López',
            'Dépor Abanca' => 'Riazor',
        ];

        foreach ($mapped as $team => $stadium) {
            $this->assertSame(
                $stadium,
                $service->rentalCatalogue($team)[0]['stadium'],
                $team
            );
        }

        // FC Badalona Women rent their usual ground (Palamós); Madrid CFF
        // is women's-only and Logroño United has no mapped men's ground —
        // both keep the unrestricted catalogue.
        $this->assertSame(
            'Nou Estadi de Palamós',
            $service->rentalCatalogue('FC Badalona Women')[0]['stadium']
        );
        $this->assertFalse($service->hasMensStadium('Madrid CFF'));
        $this->assertFalse($service->hasMensStadium('Logroño United'));
    }
}

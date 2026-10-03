<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Modules\Competition\Configs\BundesligaConfig;
use App\Modules\Competition\Configs\EredivisieConfig;
use App\Modules\Competition\Configs\LaLiga2Config;
use App\Modules\Competition\Configs\LaLigaConfig;
use App\Modules\Competition\Configs\LigaMXFemenilConfig;
use App\Modules\Competition\Configs\Ligue1Config;
use App\Modules\Competition\Configs\NWSLConfig;
use App\Modules\Competition\Configs\PremierLeagueConfig;
use App\Modules\Competition\Configs\PrimeiraLigaConfig;
use App\Modules\Competition\Configs\PrimeraRFEFConfig;
use App\Modules\Competition\Configs\SerieAConfig;
use App\Modules\Competition\Configs\WSL2Config;
use Tests\TestCase;

/**
 * Fixes 1-3 de la revisión de medios (grupo 04): las zonas de descenso y
 * los targetPosition de SEASON_GOALS usaban números de las ligas
 * masculinas. Verificado contra config/countries.php (nº real de equipos
 * y relegated_positions de cada liga femenina).
 */
class CompetitionZonesAndGoalsFixTest extends TestCase
{
    // ── Fix 1: zonas de descenso ─────────────────────────────────────────

    public function test_esp2_relegation_zone_is_11_to_14(): void
    {
        // ESP2: 14 equipos, relegated_positions [11,12,13,14] (countries.ES).
        $this->assertZone((new LaLiga2Config())->getStandingsZones(), 'game.relegation', 11, 14);
    }

    public function test_eng1_relegation_zone_is_14_with_playoff_at_13(): void
    {
        // ENG1: 14 equipos; el 14º baja directo y el 13º va al playoff ENGPO.
        $zones = (new PremierLeagueConfig())->getStandingsZones();
        $this->assertZone($zones, 'game.relegation', 14, 14);
        $this->assertZone($zones, 'game.relegation_playoff', 13, 13);
    }

    public function test_deu1_relegation_zone_is_13_to_14(): void
    {
        $this->assertZone((new BundesligaConfig())->getStandingsZones(), 'game.relegation', 13, 14);
    }

    public function test_ita1_relegation_zone_is_12(): void
    {
        $this->assertZone((new SerieAConfig())->getStandingsZones(), 'game.relegation', 12, 12);
    }

    public function test_fra1_relegation_zone_is_11_to_12(): void
    {
        $this->assertZone((new Ligue1Config())->getStandingsZones(), 'game.relegation', 11, 12);
    }

    public function test_ned1_and_por1_have_no_relegation_zone(): void
    {
        // NED1 y POR1: 10 equipos y único tier jugable → no hay descenso.
        foreach ([new EredivisieConfig(), new PrimeiraLigaConfig()] as $config) {
            foreach ($config->getStandingsZones() as $zone) {
                $this->assertNotSame('game.relegation', $zone['label']);
            }
        }
    }

    public function test_nwsl_and_ligamx_have_no_relegation_zone(): void
    {
        // USA1 (16) y MEX1 (18) no tienen relegated_positions en la config.
        $this->assertSame([], (new NWSLConfig())->getStandingsZones());
        $this->assertSame([], (new LigaMXFemenilConfig())->getStandingsZones());
    }

    public function test_wsl2_has_no_relegation_zone_but_playoff_zone_for_2nd(): void
    {
        // ENG2: 12 equipos, último nivel → sin descenso; el 2º va al ENGPO.
        $zones = (new WSL2Config())->getStandingsZones();
        foreach ($zones as $zone) {
            $this->assertNotSame('game.relegation', $zone['label']);
        }
        $this->assertZone($zones, 'game.promotion_playoff', 2, 2);
    }

    // ── Fix 2: playoff de Primera RFEF ────────────────────────────────────

    public function test_primera_rfef_playoff_zone_is_2_to_3(): void
    {
        // SegundaFederacionPlayoffGenerator::getQualifyingPositions() = [2, 3].
        $this->assertZone((new PrimeraRFEFConfig())->getStandingsZones(), 'game.promotion_playoff', 2, 3);
    }

    // ── Fix 3: targetPosition alcanzables ─────────────────────────────────

    /**
     * Ningún objetivo puede pedir una posición imposible: con
     * evaluatePerformance() usando actual <= target, un target inalcanzable
     * se cumple siempre (incluso quedando último) y la directiva nunca
     * presiona ni despide.
     */
    public function test_all_season_goal_targets_are_reachable(): void
    {
        $cases = [
            [new LaLigaConfig(), 16],
            [new LaLiga2Config(), 14],
            [new PremierLeagueConfig(), 14],
            [new BundesligaConfig(), 14],
            [new SerieAConfig(), 12],
            [new Ligue1Config(), 12],
            [new EredivisieConfig(), 10],
            [new PrimeiraLigaConfig(), 10],
            [new NWSLConfig(), 16],
            [new LigaMXFemenilConfig(), 18],
            [new WSL2Config(), 12],
            [new PrimeraRFEFConfig(), 14],
        ];

        foreach ($cases as [$config, $teams]) {
            foreach ($config->getAvailableGoals() as $goal => $def) {
                $target = $def['targetPosition'];
                $this->assertGreaterThanOrEqual(1, $target, get_class($config) . "::$goal < 1");
                $this->assertLessThanOrEqual(
                    $teams,
                    $target,
                    get_class($config) . "::$goal target {$target} imposible con {$teams} equipos"
                );
            }
        }
    }

    public function test_survival_targets_match_the_real_relegation_lines(): void
    {
        // La supervivencia debe ser la última plaza que evita el descenso
        // (o evitar el farolillo donde no hay descenso), nunca una plaza de
        // descenso: si no, descendiendo se "cumple" el objetivo.
        $this->assertSame(14, (new LaLigaConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL));      // ESP1: bajan 15-16
        $this->assertSame(10, (new LaLiga2Config())->getGoalTargetPosition(Game::GOAL_SURVIVAL));     // ESP2: bajan 11-14
        $this->assertSame(12, (new PremierLeagueConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL)); // ENG1: baja el 14, playoff el 13
        $this->assertSame(12, (new BundesligaConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL));  // DEU1: bajan 13-14
        $this->assertSame(11, (new SerieAConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL));      // ITA1: baja el 12
        $this->assertSame(10, (new Ligue1Config())->getGoalTargetPosition(Game::GOAL_SURVIVAL));       // FRA1: bajan 11-12
        $this->assertSame(9, (new EredivisieConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL));    // NED1: sin descenso
        $this->assertSame(9, (new PrimeiraLigaConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL));  // POR1: sin descenso
        $this->assertSame(13, (new PrimeraRFEFConfig())->getGoalTargetPosition(Game::GOAL_SURVIVAL));  // ESP3: sin descenso
    }

    public function test_europe_and_playoff_targets_match_real_slots(): void
    {
        $this->assertSame(3, (new LaLigaConfig())->getGoalTargetPosition(Game::GOAL_EUROPA_LEAGUE));       // ESP1: Europa 1-3
        $this->assertSame(5, (new PremierLeagueConfig())->getGoalTargetPosition(Game::GOAL_EUROPA_LEAGUE)); // ENG1: Europa 1-5
        $this->assertSame(5, (new BundesligaConfig())->getGoalTargetPosition(Game::GOAL_EUROPA_LEAGUE));   // DEU1: Europa 1-5
        $this->assertSame(4, (new SerieAConfig())->getGoalTargetPosition(Game::GOAL_EUROPA_LEAGUE));        // ITA1: Europa 1-4
        $this->assertSame(3, (new EredivisieConfig())->getGoalTargetPosition(Game::GOAL_EUROPA_LEAGUE));    // NED1: Europa 1-3
        $this->assertSame(3, (new PrimeiraLigaConfig())->getGoalTargetPosition(Game::GOAL_EUROPA_LEAGUE));  // POR1: Europa 1-3
        $this->assertSame(5, (new LaLiga2Config())->getGoalTargetPosition(Game::GOAL_PLAYOFF));             // ESP2: playoff 2-5
        $this->assertSame(3, (new PrimeraRFEFConfig())->getGoalTargetPosition(Game::GOAL_PLAYOFF));         // ESP3: playoff 2-3
        $this->assertSame(1, (new LaLiga2Config())->getGoalTargetPosition(Game::GOAL_PROMOTION));           // ESP2: ascenso directo = 1º
    }

    private function assertZone(array $zones, string $label, int $min, int $max): void
    {
        $matches = array_values(array_filter(
            $zones,
            fn (array $z) => $z['label'] === $label && $z['minPosition'] === $min && $z['maxPosition'] === $max
        ));

        $this->assertNotEmpty(
            $matches,
            "Zona '{$label}' {$min}-{$max} no encontrada en: " . json_encode($zones)
        );
    }
}

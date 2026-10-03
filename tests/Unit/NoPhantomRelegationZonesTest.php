<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA review: six configs painted relegation zones for leagues whose
 * lower division does not exist in the game (no promotion rule lists
 * them as top_division in config/countries.php, so no relegation is ever
 * modeled): SecondeLigue 10–11, Zweite 12–14, Serie B Femminile 13–14,
 * Swiss Super League 9–10, Argentine Primera 15–16 and Brasileirão
 * Feminino 15–16. The zones implied a relegation that never happens.
 */
class NoPhantomRelegationZonesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function configsWithoutLowerDivision(): array
    {
        return [
            'FRA2' => ['App\\Modules\\Competition\\Configs\\SecondeLigueConfig'],
            'DEU2' => ['App\\Modules\\Competition\\Configs\\ZweiteFrauenBundesligaConfig'],
            'ITA2' => ['App\\Modules\\Competition\\Configs\\SerieBFemminileConfig'],
            'SUI1' => ['App\\Modules\\Competition\\Configs\\SwissSuperLeagueConfig'],
            'ARG1' => ['App\\Modules\\Competition\\Configs\\ArgentinePrimeraConfig'],
            'BRA1' => ['App\\Modules\\Competition\\Configs\\BrasileiraoFemininoConfig'],
        ];
    }

    #[DataProvider('configsWithoutLowerDivision')]
    public function test_no_relegation_zone_is_painted(string $configClass): void
    {
        $config = new $configClass();

        $labels = array_column($config->getStandingsZones(), 'label');

        $this->assertNotContains(
            'game.relegation',
            $labels,
            "{$configClass} must not paint a relegation zone: no lower division exists in the game."
        );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA review: ARG1 (LIBERTADORES [1,2]), BRA1 (LIBERTADORES [1,2,3,4]),
 * MEX1 and USA1 (CONCACHAMPIONS [1,2,3]) define continental slots in
 * config/countries.php but painted no continental zone — the qualification
 * race was invisible in the standings. The zones must mirror the slots.
 */
class ContinentalZonesPaintedTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function continentalZones(): array
    {
        return [
            'ARG1' => ['App\\Modules\\Competition\\Configs\\ArgentinePrimeraConfig', 'game.libertadores', 1, 2],
            'BRA1' => ['App\\Modules\\Competition\\Configs\\BrasileiraoFemininoConfig', 'game.libertadores', 1, 4],
            'MEX1' => ['App\\Modules\\Competition\\Configs\\LigaMXFemenilConfig', 'game.concachampions', 1, 3],
            'USA1' => ['App\\Modules\\Competition\\Configs\\NWSLConfig', 'game.concachampions', 1, 3],
        ];
    }

    #[DataProvider('continentalZones')]
    public function test_continental_zone_matches_the_configured_slots(
        string $configClass,
        string $label,
        int $minPosition,
        int $maxPosition,
    ): void {
        $zones = (new $configClass())->getStandingsZones();

        $continental = array_values(array_filter(
            $zones,
            fn (array $z) => $z['label'] === $label
        ));

        $this->assertCount(1, $continental, "{$configClass} must paint exactly one {$label} zone.");
        $this->assertSame($minPosition, $continental[0]['minPosition']);
        $this->assertSame($maxPosition, $continental[0]['maxPosition']);
    }

    #[DataProvider('continentalZones')]
    public function test_continental_zone_label_exists_in_all_locales(
        string $configClass,
        string $label,
        int $minPosition,
        int $maxPosition,
    ): void {
        [$file, $key] = explode('.', $label, 2);

        foreach (['es', 'en', 'de', 'fr', 'pt'] as $locale) {
            $strings = include base_path("lang/{$locale}/{$file}.php");
            $this->assertArrayHasKey($key, $strings, "missing lang key '{$label}' in {$locale}/{$file}.php");
        }
    }
}

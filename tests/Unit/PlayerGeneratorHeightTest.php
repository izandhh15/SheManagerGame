<?php

namespace Tests\Unit;

use App\Modules\Squad\Services\PlayerGeneratorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA review: PlayerGeneratorService::generateHeight() compared against
 * 'GK' (dead branch — every caller passes 'Goalkeeper'/null) and used
 * masculine ranges (GK 185–200, outfield 168–196) in a women's game.
 *
 * Ranges now come from the real-player dataset (data-raw, 3,315 players
 * with heights, Oct 2026): GK 160–191, DEF 149–187, MID 141–188,
 * FWD 146–185 — exercised here through the public pickRandomIdentity().
 */
class PlayerGeneratorHeightTest extends TestCase
{
    /**
     * @return array<string, array{string|null, int, int}>
     */
    public static function heightRanges(): array
    {
        return [
            'goalkeeper uses the GK range' => ['Goalkeeper', 160, 191],
            'defender uses the DEF range' => ['Centre-Back', 149, 187],
            'midfielder uses the MID range' => ['Central Midfield', 141, 188],
            'forward uses the FWD range' => ['Centre-Forward', 146, 185],
            'null position falls back to the MID range' => [null, 141, 188],
        ];
    }

    #[DataProvider('heightRanges')]
    public function test_generated_height_falls_in_the_documented_female_range(?string $position, int $min, int $max): void
    {
        $service = app(PlayerGeneratorService::class);

        for ($i = 0; $i < 60; $i++) {
            $identity = $service->pickRandomIdentity(position: $position);
            $cm = $this->heightToCm($identity['height']);

            $this->assertGreaterThanOrEqual($min, $cm, "height {$identity['height']} below {$min}cm for position " . var_export($position, true));
            $this->assertLessThanOrEqual($max, $cm, "height {$identity['height']} above {$max}cm for position " . var_export($position, true));
        }
    }

    public function test_goalkeeper_branch_is_reachable(): void
    {
        // Guards the old dead-branch regression: with the 'GK' comparison
        // every keeper silently got the outfield range. 60 draws must stay
        // inside the GK band, which no longer overlaps the old male range
        // top (185–200) except at its lower edge.
        $service = app(PlayerGeneratorService::class);

        $tall = 0;
        for ($i = 0; $i < 60; $i++) {
            $cm = $this->heightToCm($service->pickRandomIdentity(position: 'Goalkeeper')['height']);
            $this->assertLessThanOrEqual(191, $cm);
            if ($cm < 168) {
                $tall++; // impossible under the old 185–200 male GK range
            }
        }
        $this->assertGreaterThan(0, $tall, 'expected some keepers below 168cm (female range), impossible with the old male 185–200 range');
    }

    private function heightToCm(string $height): int
    {
        $this->assertMatchesRegularExpression('/^(\d+),(\d{2})m$/', $height, 'height format "M,CCm"');

        return (int) $height[0] * 100 + (int) substr($height, 2, 2);
    }
}

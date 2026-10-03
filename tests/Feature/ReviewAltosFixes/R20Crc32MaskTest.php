<?php

namespace Tests\Feature\ReviewAltosFixes;

use Tests\TestCase;

/**
 * R20 [ALTA] (C6 family): two crc32() index computations were not masked
 * with & 0x7FFFFFFF — on 32-bit PHP (Wasmer Edge) crc32() returns negative
 * ints ~50% of the time, so:
 *   - ManagerPressureService: `$variant === 1` never matched variant 2
 *     (negative odds gave -1 % 2 === -1);
 *   - MediaOutletService::deterministicOutlet(): the negative index fell
 *     through to the 'EFE Deportes' fallback instead of the real outlet.
 * Both are now masked: (crc32(...) & 0x7FFFFFFF) % n.
 */
class R20Crc32MaskTest extends TestCase
{
    /** @return array<int, string> */
    private function seeds(): array
    {
        // Wide spread of seeds, including ones that are negative when
        // crc32() is emulated as signed 32-bit.
        return [
            'pv-article-1',
            'wc2026-final-esp-bra',
            'game-9f3c2a1b-4d5e-6f70-8192-a3b4c5d6e7f8',
            str_repeat('x', 64),
            'outlet-seed-ñandú-42',
            '0', '1', '123456789', 'tournament-round-3',
        ];
    }

    /**
     * Emulate 32-bit PHP: wrap the (64-bit) crc32() result into a signed
     * int32, exactly what Wasmer Edge's 32-bit build returns.
     */
    private function crc32AsInt32(string $seed): int
    {
        $raw = crc32($seed);

        return $raw > 0x7FFFFFFF ? $raw - 0x100000000 : $raw;
    }

    public function test_masked_indices_are_never_negative_even_on_emulated_32bit(): void
    {
        $foundNegative = false;

        foreach ($this->seeds() as $seed) {
            $signed = $this->crc32AsInt32($seed);

            if ($signed < 0) {
                $foundNegative = true;
            }

            // ManagerPressureService: (crc32(...) & 0x7FFFFFFF) % 2
            $variant = ($signed & 0x7FFFFFFF) % 2;
            $this->assertGreaterThanOrEqual(0, $variant, "seed {$seed}: variant must be >= 0");
            $this->assertLessThanOrEqual(1, $variant, "seed {$seed}: variant must be <= 1");

            // MediaOutletService: (crc32($seed) & 0x7FFFFFFF) % max(1, count)
            foreach ([1, 3, 7, 12] as $outletCount) {
                $index = ($signed & 0x7FFFFFFF) % max(1, $outletCount);
                $this->assertGreaterThanOrEqual(0, $index, "seed {$seed}: outlet index must be >= 0");
                $this->assertLessThan($outletCount, $index, "seed {$seed}: outlet index in range");
            }
        }

        $this->assertTrue(
            $foundNegative,
            'At least one seed must be negative under 32-bit emulation, or the mask is never exercised'
        );
    }

    public function test_source_files_apply_the_mask(): void
    {
        $pressure = file_get_contents(
            app_path('Modules/Media/Services/ManagerPressureService.php')
        );
        $outlet = file_get_contents(
            app_path('Modules/Media/Services/MediaOutletService.php')
        );

        $this->assertStringContainsString(
            '(crc32($game->id.\'pv\'.$round.$team->id) & 0x7FFFFFFF) % 2',
            $pressure,
            'ManagerPressureService must mask crc32 before % 2'
        );
        $this->assertStringContainsString(
            '(crc32($seed) & 0x7FFFFFFF) % max(1, count($outlets))',
            $outlet,
            'MediaOutletService must mask crc32 before the modulo index'
        );
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Media\Services\MediaOutletService;
use Tests\TestCase;

/**
 * FEATURE 8/9 — media outlet logo audit (2026-10-02).
 *
 * Every outlet defined in data/media_outlets.json must resolve to a logo
 * file that actually exists under public/images/media-logos, and every
 * outlet referenced from teams/countries/default must be defined.
 *
 * Background: several logos were photos or screenshots of the wrong thing
 * (El Chiringuito was a photo of the TV set, Diario Vasco a person holding
 * a newspaper, Levante-EMV an old front-page scan, UOL Esporte a website
 * screenshot, …) and Tribuna Deportiva's white-on-transparent logo was
 * invisible on the white pill. Wrong ones were replaced with neutral
 * initials badges (never another brand's logo).
 */
class MediaLogosTest extends TestCase
{
    private function outletsData(): array
    {
        $path = base_path('data/media_outlets.json');
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);
        $this->assertIsArray($data, 'media_outlets.json must be valid JSON');

        return $data;
    }

    public function test_every_outlet_has_an_existing_logo_file(): void
    {
        $data = $this->outletsData();
        $dir = public_path('images/media-logos');

        foreach ($data['outlets'] as $name => $outlet) {
            $this->assertNotEmpty(
                $outlet['logo'] ?? null,
                "Outlet '{$name}' has no logo configured"
            );
            $this->assertFileExists(
                $dir.'/'.$outlet['logo'],
                "Outlet '{$name}' references missing logo file '{$outlet['logo']}'"
            );
        }
    }

    public function test_all_referenced_outlets_are_defined(): void
    {
        $data = $this->outletsData();
        $defined = array_keys($data['outlets']);
        $missing = [];

        $check = function (array $list, string $where) use ($defined, &$missing) {
            foreach ($list as $name) {
                if (! in_array($name, $defined, true)) {
                    $missing[] = "{$where}: {$name}";
                }
            }
        };

        foreach ($data['teams'] ?? [] as $team => $list) {
            $check($list, "teams[{$team}]");
        }
        foreach ($data['countries'] ?? [] as $country => $list) {
            $check($list, "countries[{$country}]");
        }
        $check($data['default'] ?? [], 'default');

        $this->assertSame([], $missing, 'Outlets referenced but not defined: '.implode(', ', $missing));
    }

    public function test_outlet_info_resolves_logo_for_every_defined_outlet(): void
    {
        $data = $this->outletsData();
        $service = app(MediaOutletService::class);

        foreach (array_keys($data['outlets']) as $name) {
            $info = $service->outletInfo($name);
            $this->assertSame($name, $info['name']);
            $this->assertNotEmpty($info['logo'], "outletInfo('{$name}') returned no logo URL");
            $this->assertNotEmpty($info['color']);
        }
    }

    public function test_logo_files_are_not_empty(): void
    {
        $data = $this->outletsData();
        $dir = public_path('images/media-logos');
        $seen = [];

        foreach ($data['outlets'] as $name => $outlet) {
            $file = $outlet['logo'];
            $this->assertGreaterThan(
                0,
                filesize($dir.'/'.$file),
                "Logo file '{$file}' for '{$name}' is empty"
            );
            $seen[$file][] = $name;
        }

        // One file per outlet: no two outlets accidentally share the same logo.
        $shared = array_filter($seen, fn ($names) => count($names) > 1);
        $this->assertSame([], $shared, 'Logo files shared by multiple outlets: '.json_encode($shared));
    }
}

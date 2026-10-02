<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameSponsorDeal;
use App\Models\Team;
use App\Modules\Commercial\Services\SponsorOfferFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regional sponsors: a brand with a `region` only sponsors clubs from that
 * region. The Universidad de Valladolid must never end up on a Getafe shirt
 * — Madrid clubs get Madrid brands (e.g. Universidad Complutense de Madrid).
 * Region-less brands (neighbourhood shops) stay universal, and clubs without
 * a mapped region keep the old country-level behaviour.
 */
class SponsorRegionTest extends TestCase
{
    use RefreshDatabase;

    private SponsorOfferFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new SponsorOfferFactory();
    }

    private function gameForTeam(string $teamName): Game
    {
        $team = Team::factory()->create(['name' => $teamName]);

        return Game::factory()->forTeam($team)->create([
            'season' => 2026,
            'current_date' => '2026-10-02',
            'country' => 'ES',
        ]);
    }

    /**
     * Draw up to $draws sponsor names for a slot+tier, clearing each offer
     * so the per-season "no duplicate brand" rule doesn't exhaust the pool.
     *
     * @return list<string>
     */
    private function drawNames(Game $game, string $tier, int $draws = 120): array
    {
        $names = [];
        for ($i = 0; $i < $draws; $i++) {
            $offer = $this->factory->createOffer($game, 'shirt', $tier);
            if ($offer === null) {
                break;
            }
            $names[] = $offer->sponsor_name;
            $offer->delete();
        }

        return $names;
    }

    // ── Getafe (madrid) — local tier ─────────────────────────────────────

    public function test_getafe_never_gets_out_of_region_local_sponsors(): void
    {
        $game = $this->gameForTeam('CD Getafe Femenino');
        $names = $this->drawNames($game, 'local');

        $this->assertNotEmpty($names);

        $forbidden = [
            'Universidad de Valladolid',
            'Universidad de Oviedo',
            'Universidad de Sevilla',
            'Universidad de Zaragoza',
            'Universidad de Castilla-La Mancha',
            'Universitat de Barcelona',
        ];
        foreach ($names as $name) {
            $this->assertNotContains($name, $forbidden, "Out-of-region sponsor offered to Getafe: {$name}");
        }
    }

    public function test_getafe_can_get_madrid_brands(): void
    {
        $game = $this->gameForTeam('CD Getafe Femenino');
        $names = $this->drawNames($game, 'local');

        // Madrid pool: Complutense + Carlos III + 6 neighbourhood shops.
        $this->assertContains('Universidad Complutense de Madrid', $names);
    }

    // ── Sporting de Gijón (asturias) — local tier ─────────────────────────

    public function test_asturian_club_gets_oviedo_but_not_valladolid(): void
    {
        $game = $this->gameForTeam('Sporting de Gijón');
        $names = $this->drawNames($game, 'local');

        $this->assertNotEmpty($names);
        $this->assertContains('Universidad de Oviedo', $names);
        $this->assertNotContains('Universidad de Valladolid', $names);
        $this->assertNotContains('Universidad Complutense de Madrid', $names);
    }

    // ── Regional tier (Mahou vs Cruzcampo) ───────────────────────────────

    public function test_regional_tier_brands_stay_in_region(): void
    {
        $game = $this->gameForTeam('CD Getafe Femenino');
        $names = $this->drawNames($game, 'regional');

        $this->assertNotEmpty($names);
        // Mahou is the only madrid-region brand in the regional pool.
        $this->assertContains('Mahou', $names);
        $this->assertNotContains('Cruzcampo', $names);
        $this->assertNotContains('Estrella Damm', $names);
        $this->assertNotContains('Estrella Galicia', $names);
    }

    // ── Unmapped club — fallback to country-level behaviour ─────────────

    public function test_unmapped_club_keeps_country_level_behaviour(): void
    {
        $game = $this->gameForTeam('Club Sin Región Mapeada');
        $names = $this->drawNames($game, 'local');

        $this->assertNotEmpty($names);
        // Without a mapped region the old behaviour applies: any Spanish
        // university can bid.
        $this->assertContains('Universidad de Valladolid', $names);
    }

    // ── Region-less brands stay universal ───────────────────────────────

    public function test_neighbourhood_shops_sponsor_anywhere(): void
    {
        $game = $this->gameForTeam('CD Getafe Femenino');
        $names = $this->drawNames($game, 'local');

        $this->assertContains('Panadería familiar del barrio', $names);
    }
}

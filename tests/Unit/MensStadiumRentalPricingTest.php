<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Stadium\Services\MensStadiumRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Men's-stadium rental, new model (0.3.9):
 *
 * F2 — Pricing (Izan's rule): grounds WITHOUT a team are FREE; all others
 * are paid to the CITY COUNCIL (never the club).
 *
 * F3 — Requests: any ground IN THE USER'S OWN COUNTRY can be requested;
 * SOME clubs refuse with deterministic excuses (same club+season => same
 * answer), not all of them.
 */
class MensStadiumRentalPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('mens_stadiums_v2');
    }

    private function service(): MensStadiumRequestService
    {
        return new MensStadiumRequestService;
    }

    public function test_teamless_ground_is_free(): void
    {
        $service = $this->service();
        $stadium = $service->stadiumByKey('La Cartuja|Ayuntamiento de Sevilla');

        $this->assertNotNull($stadium);
        $this->assertNull($stadium['club']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id, 'team_id' => $team->id, 'season' => '2026',
            'current_date' => '2026-09-01',
        ]);
        $match = GameMatch::factory()->create([
            'game_id' => $game->id, 'home_team_id' => $team->id,
            'scheduled_date' => '2026-10-15 18:00:00', 'played' => false,
        ]);

        $quote = $service->quoteForMatch($match, $game, $stadium);

        $this->assertTrue($quote['accepted']);
        $this->assertSame(0, $quote['price']);
        $this->assertContains('municipal_free', $quote['reasons']);
    }

    public function test_club_ground_is_paid_to_council(): void
    {
        $service = $this->service();

        // Find a Spanish club ground that is NOT difficult this season.
        $candidates = array_filter(
            $service->rentalCatalogue('Valencia CF Femenino', 'ES'),
            fn (array $s) => ($s['club'] ?? null) !== null
                && ! $service->isClubDifficult($s['club'], '2026')
        );
        $this->assertNotEmpty($candidates, 'no non-difficult Spanish ground found');
        $stadium = array_values($candidates)[0];

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id, 'team_id' => $team->id, 'season' => '2026',
            'current_date' => '2026-09-01',
        ]);
        $match = GameMatch::factory()->create([
            'game_id' => $game->id, 'home_team_id' => $team->id,
            'scheduled_date' => '2026-10-15 18:00:00', 'played' => false,
        ]);

        $quote = $service->quoteForMatch($match, $game, $stadium);

        $this->assertTrue($quote['accepted']);
        $this->assertSame((int) $stadium['rental_price'], $quote['price']);
        $this->assertGreaterThan(0, $quote['price']);
        $this->assertContains('council_paid', $quote['reasons']);
    }

    public function test_catalogue_is_limited_to_user_country(): void
    {
        $service = $this->service();

        $es = $service->rentalCatalogue('Valencia CF Femenino', 'ES');
        $this->assertNotEmpty($es);
        foreach ($es as $s) {
            $this->assertSame('España', $s['country'], $s['stadium']);
        }

        // No English ground sneaks into the Spanish catalogue.
        $this->assertEmpty(array_filter($es, fn (array $s) => ($s['country'] ?? null) === 'Inglaterra'));

        // Cross-country rental is rejected.
        $this->assertFalse($service->isRentableBy('Valencia CF Femenino', 'Emirates Stadium|Arsenal', 'ES'));
        $this->assertTrue($service->isRentableBy('Valencia CF Femenino', 'Mestalla|Valencia CF', 'ES'));
    }

    public function test_difficult_clubs_refuse_with_deterministic_excuse(): void
    {
        $service = $this->service();

        // Find a difficult Spanish club this season.
        $difficult = null;
        foreach ($service->rentalCatalogue('Valencia CF Femenino', 'ES') as $s) {
            if (($s['club'] ?? null) !== null && $service->isClubDifficult($s['club'], '2026')) {
                $difficult = $s;
                break;
            }
        }
        $this->assertNotNull($difficult, 'no difficult club found (expected ~30%)');

        // Deterministic: same answer every time.
        $this->assertTrue($service->isClubDifficult($difficult['club'], '2026'));
        $this->assertSame(
            $service->deterministicExcuse($difficult['club'], '2026'),
            $service->deterministicExcuse($difficult['club'], '2026')
        );

        // Not ALL clubs are difficult.
        $easy = array_filter(
            $service->rentalCatalogue('Valencia CF Femenino', 'ES'),
            fn (array $s) => ($s['club'] ?? null) !== null && ! $service->isClubDifficult($s['club'], '2026')
        );
        $this->assertNotEmpty($easy);

        // The quote carries the refusal.
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id, 'team_id' => $team->id, 'season' => '2026',
            'current_date' => '2026-09-01',
        ]);
        $match = GameMatch::factory()->create([
            'game_id' => $game->id, 'home_team_id' => $team->id,
            'scheduled_date' => '2026-10-15 18:00:00', 'played' => false,
        ]);

        $quote = $service->quoteForMatch($match, $game, $difficult);
        $this->assertFalse($quote['accepted']);
        $this->assertStringStartsWith('excuse_', $quote['reasons'][0]);
    }
}

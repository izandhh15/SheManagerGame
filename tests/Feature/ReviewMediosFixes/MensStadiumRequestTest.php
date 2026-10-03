<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Stadium\Services\MensStadiumRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fixes 4-5: MensStadiumRequestService
 *  - confirmQuote() re-validates the MAX_PER_SEASON limit (inside the
 *    transaction) and re-reads the budget under a lock (TOCTOU)
 *  - usesThisSeason() counts only matches flagged mens_stadium_rental —
 *    neutral cup finals must not eat the quota
 */
class MensStadiumRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Team $opponent;
    private Game $game;
    private GameInvestment $investment;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'ESP1']);
        Competition::factory()->create(['id' => 'ESPCUP']);

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['country' => 'ES']);
        $this->opponent = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $this->investment = GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'transfer_budget' => 100_000_000_00,
        ]);
    }

    private function homeMatch(): GameMatch
    {
        return GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->team->id,
            'away_team_id' => $this->opponent->id,
            'played' => false,
            'scheduled_date' => '2026-10-15',
        ]);
    }

    private function quote(int $price = 0): array
    {
        return [
            'price' => $price,
            'stadium' => ['stadium' => 'Mestalla', 'capacity' => 49430],
        ];
    }

    public function test_neutral_cup_final_does_not_consume_quota(): void
    {
        // A neutral cup final (set by CupDrawService, flag NOT set).
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESPCUP',
            'home_team_id' => $this->team->id,
            'away_team_id' => $this->opponent->id,
            'played' => false,
            'neutral_venue_name' => 'La Cartuja',
            'neutral_venue_capacity' => 60000,
        ]);

        $service = app(MensStadiumRequestService::class);

        $this->assertSame(0, $service->usesThisSeason($this->game));
        $this->assertTrue($service->canRequest($this->game));
    }

    public function test_rental_counts_toward_quota(): void
    {
        $service = app(MensStadiumRequestService::class);

        $result = $service->confirmQuote($this->homeMatch(), $this->game->fresh(), $this->quote());

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $service->usesThisSeason($this->game->fresh()));
    }

    public function test_fourth_rental_is_rejected(): void
    {
        $service = app(MensStadiumRequestService::class);

        for ($i = 0; $i < MensStadiumRequestService::MAX_PER_SEASON; $i++) {
            $result = $service->confirmQuote($this->homeMatch(), $this->game->fresh(), $this->quote());
            $this->assertTrue($result['ok'], "rental {$i} should succeed");
        }

        $fourth = $service->confirmQuote($this->homeMatch(), $this->game->fresh(), $this->quote());

        $this->assertFalse($fourth['ok']);
        $this->assertSame(
            MensStadiumRequestService::MAX_PER_SEASON,
            $service->usesThisSeason($this->game->fresh())
        );
    }

    public function test_paid_rental_charges_budget_once(): void
    {
        $service = app(MensStadiumRequestService::class);
        $before = (int) $this->investment->fresh()->transfer_budget;

        // €1 000 000 → 100 000 000 cents.
        $result = $service->confirmQuote($this->homeMatch(), $this->game->fresh(), $this->quote(1_000_000));

        $this->assertTrue($result['ok']);
        $this->assertSame($before - 100_000_000, (int) $this->investment->fresh()->transfer_budget);
        $this->assertDatabaseHas('financial_transactions', [
            'game_id' => $this->game->id,
            'category' => 'venue_rent',
            'amount' => 100_000_000,
        ]);
    }
}

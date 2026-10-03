<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\Team;
use App\Models\User;
use App\Modules\Government\Services\GovernmentFriendlyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fix 1: GovernmentFriendlyService::accept()
 *  - re-checks hasCompetitionInProgress() at accept time
 *  - runs in a DB transaction with the offer row locked (double accept
 *    must not credit the federation budget twice)
 */
class GovernmentFriendlyAcceptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $nationalTeam;
    private Team $opponent;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->nationalTeam = Team::factory()->create(['type' => 'national', 'country' => 'ES']);
        $this->opponent = Team::factory()->create(['type' => 'national', 'country' => 'FR']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->nationalTeam->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
            'federation_budget' => 0,
        ]);
    }

    private function makeOffer(int $amount = 2_000_000): GameNotification
    {
        return GameNotification::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER,
            'title' => 'Offer',
            'priority' => GameNotification::PRIORITY_WARNING,
            'metadata' => [
                'government' => 'Qatar',
                'amount' => $amount,
                'opponent_team_id' => $this->opponent->id,
                'opponent_name' => $this->opponent->name,
                'status' => 'pending',
            ],
        ]);
    }

    public function test_accept_credits_budget_once_and_schedules_match(): void
    {
        $service = app(GovernmentFriendlyService::class);
        $offer = $this->makeOffer();

        $result = $service->accept($offer);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        $this->assertSame(2_000_000, (int) $this->game->fresh()->federation_budget);
        $this->assertSame('accepted', $offer->fresh()->metadata['status']);
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'FRIENDLY',
            'government_sponsored' => true,
        ]);
    }

    public function test_double_accept_credits_budget_only_once(): void
    {
        $service = app(GovernmentFriendlyService::class);
        $offer = $this->makeOffer();

        $first = $service->accept($offer);
        $second = $service->accept($offer->fresh());

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok']);
        $this->assertSame('game.gov_friendly_already_answered', $second['error']);
        $this->assertSame(2_000_000, (int) $this->game->fresh()->federation_budget);
        $this->assertSame(1, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'FRIENDLY')->count());
    }

    public function test_accept_rejected_when_competition_in_progress(): void
    {
        Competition::factory()->create(['id' => 'WWCU27']);

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'WWCU27',
            'played' => false,
            'home_team_id' => $this->nationalTeam->id,
            'away_team_id' => $this->opponent->id,
        ]);

        $service = app(GovernmentFriendlyService::class);
        $result = $service->accept($this->makeOffer());

        $this->assertFalse($result['ok']);
        $this->assertSame(0, (int) $this->game->fresh()->federation_budget);
        $this->assertSame(0, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'FRIENDLY')->count());
    }
}

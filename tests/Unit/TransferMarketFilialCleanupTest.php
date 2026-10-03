<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferListing;
use App\Modules\Transfer\Services\TransferMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: TransferMarketService::refreshListings() deleted expired
 * listings with `team_id != team_id` while the AI-listing count right below
 * used `whereNotIn(userTeamIds())`. The user's reserve-team listings are
 * the user's listings too — the sweep silently wiped them on expiry while
 * first-team listings were preserved. The delete must be filial-aware.
 */
class TransferMarketFilialCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_preserves_the_users_reserve_listings_but_cleans_ai_expired(): void
    {
        $firstTeam = Team::factory()->create(['name' => 'Atlético de Madrid']);
        $reserveTeam = Team::factory()->create([
            'name' => 'Atlético Madrileño',
            'parent_team_id' => $firstTeam->id,
        ]);
        $aiTeam = Team::factory()->create(['name' => 'AI Club']);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'team_id' => $firstTeam->id,
            'reserve_team_id' => $reserveTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        $expiredAt = '2025-06-01'; // older than LISTING_EXPIRY_DAYS (30)

        $aiListing = $this->makeListing($game, $aiTeam, $expiredAt);
        $firstTeamListing = $this->makeListing($game, $firstTeam, $expiredAt);
        $reserveListing = $this->makeListing($game, $reserveTeam, $expiredAt);

        app(TransferMarketService::class)->refreshListings($game);

        $this->assertNull(TransferListing::find($aiListing->id), 'expired AI listing must be cleaned');
        $this->assertNotNull(TransferListing::find($firstTeamListing->id), 'user first-team listing must be preserved');
        $this->assertNotNull(TransferListing::find($reserveListing->id), 'user reserve-team listing must be preserved like the first team');
    }

    public function test_refresh_keeps_fresh_ai_listings(): void
    {
        $firstTeam = Team::factory()->create(['name' => 'Atlético de Madrid']);
        $aiTeam = Team::factory()->create(['name' => 'AI Club']);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'team_id' => $firstTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        $freshListing = $this->makeListing($game, $aiTeam, '2025-08-10');

        app(TransferMarketService::class)->refreshListings($game);

        $this->assertNotNull(TransferListing::find($freshListing->id), 'fresh AI listing must survive the sweep');
    }

    private function makeListing(Game $game, Team $team, string $listedAt): TransferListing
    {
        $player = GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create(['date_of_birth' => '1998-06-15']);

        return TransferListing::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'team_id' => $team->id,
            'status' => TransferListing::STATUS_LISTED,
            'listed_at' => $listedAt,
            'asking_price' => 1_000_000,
        ]);
    }
}

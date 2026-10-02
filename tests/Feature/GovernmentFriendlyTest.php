<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\Team;
use App\Models\User;
use App\Modules\Government\Services\GovernmentFriendlyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernmentFriendlyTest extends TestCase
{
    use RefreshDatabase;

    private function nationalGame(User $user, string $teamName = 'España'): Game
    {
        $team = Team::factory()->create(['name' => $teamName, 'type' => 'national', 'country' => 'ES']);
        Team::factory()->create(['name' => 'Francia Test', 'type' => 'national', 'country' => 'FR']);

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-09-01',
            'federation_budget' => 8000000,
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);
    }

    public function test_no_offer_while_competition_in_progress(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);

        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'WOLYMP',
            'home_team_id' => $game->team_id,
            'away_team_id' => Team::factory()->create(['type' => 'national'])->id,
            'scheduled_date' => '2026-10-01',
            'played' => false,
        ]);

        $service = app(GovernmentFriendlyService::class);
        $this->assertTrue($service->hasCompetitionInProgress($game));
        $this->assertNull($service->maybeOffer($game));
    }

    public function test_offer_accept_schedules_friendly_and_pays(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);
        $service = app(GovernmentFriendlyService::class);

        $this->assertFalse($service->hasCompetitionInProgress($game));

        // Force an offer (bypass the 35% roll by calling repeatedly is
        // flaky; instead build the notification directly through the
        // service's accept path with a crafted offer).
        $opponent = Team::where('type', 'national')->where('id', '!=', $game->team_id)->first();
        $notification = GameNotification::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_WARNING,
            'metadata' => [
                'government' => 'Qatar',
                'amount' => 2000000,
                'opponent_team_id' => $opponent->id,
                'opponent_name' => $opponent->name,
                'status' => 'pending',
            ],
            'game_date' => $game->current_date,
        ]);

        $result = $service->accept($notification);
        $this->assertTrue($result['ok']);

        // Friendly scheduled…
        $this->assertTrue(GameMatch::where('game_id', $game->id)
            ->where('competition_id', 'FRIENDLY')
            ->where('government_sponsored', true)
            ->exists());

        // …and the federation got paid.
        $this->assertSame(10000000, (int) $game->fresh()->federation_budget);

        // Can't accept twice.
        $again = $service->accept($notification->fresh());
        $this->assertFalse($again['ok']);
    }

    public function test_offer_reject_dismisses(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);
        $service = app(GovernmentFriendlyService::class);

        $opponent = Team::where('type', 'national')->where('id', '!=', $game->team_id)->first();
        $notification = GameNotification::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_WARNING,
            'metadata' => ['government' => 'Qatar', 'amount' => 1000000, 'opponent_team_id' => $opponent->id, 'status' => 'pending'],
            'game_date' => $game->current_date,
        ]);

        $service->reject($notification);

        $this->assertSame('rejected', $notification->fresh()->metadata['status']);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertFalse(GameMatch::where('game_id', $game->id)->where('competition_id', 'FRIENDLY')->exists());
    }

    public function test_offer_page_renders(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);

        $response = $this->actingAs($user)->get(route('game.government-friendly', $game->id));
        $response->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\Team;
use App\Models\User;
use App\Modules\Government\Services\GovernmentVenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GovernmentVenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('mens_stadiums_v2');

        \Illuminate\Support\Facades\DB::table('competitions')->updateOrInsert(
            ['id' => 'FRIENDLY'],
            ['name' => 'Friendly', 'country' => 'XX', 'tier' => 0, 'type' => 'cup', 'handler_type' => 'friendly', 'season' => '2026']
        );
    }

    private function nationalGame(User $user): Game
    {
        $team = Team::factory()->create(['name' => 'España', 'type' => 'national', 'country' => 'ES']);
        $opp = Team::factory()->create(['name' => 'Francia', 'type' => 'national', 'country' => 'FR']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-09-01',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);

        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'FRIENDLY',
            'home_team_id' => $team->id,
            'away_team_id' => $opp->id,
            'scheduled_date' => '2026-10-01 18:00:00',
            'played' => false,
        ]);

        return $game;
    }

    public function test_accept_moves_next_home_match_to_offered_stadium(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);
        $service = app(GovernmentVenueService::class);

        $match = GameMatch::where('game_id', $game->id)->first();

        $notification = GameNotification::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_VENUE_OFFER,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_INFO,
            'metadata' => [
                'government' => 'Generalitat Valenciana',
                'stadiums' => ['Castalia', 'José Rico Pérez', 'Manuel Martínez Valero'],
                'match_id' => $match->id,
                'status' => 'pending',
            ],
            'game_date' => $game->current_date,
        ]);

        $result = $service->accept($notification, 'Castalia');
        $this->assertTrue($result['ok']);

        $match->refresh();
        $this->assertSame('Castalia', $match->neutral_venue_name);
        $this->assertTrue((bool) $match->government_sponsored);
        $this->assertSame('accepted', $notification->fresh()->metadata['status']);

        // Can't accept a stadium that wasn't offered.
        $notification2 = GameNotification::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_VENUE_OFFER,
            'title' => 'Test 2',
            'priority' => GameNotification::PRIORITY_INFO,
            'metadata' => [
                'government' => 'Generalitat Valenciana',
                'stadiums' => ['Castalia'],
                'match_id' => $match->id,
                'status' => 'pending',
            ],
            'game_date' => $game->current_date,
        ]);
        $bad = $service->accept($notification2, 'Camp Nou');
        $this->assertFalse($bad['ok']);
    }

    public function test_reject_dismisses(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);
        $service = app(GovernmentVenueService::class);

        $notification = GameNotification::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_VENUE_OFFER,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_INFO,
            'metadata' => ['government' => 'Generalitat Valenciana', 'stadiums' => ['Castalia'], 'status' => 'pending'],
            'game_date' => $game->current_date,
        ]);

        $service->reject($notification);

        $this->assertSame('rejected', $notification->fresh()->metadata['status']);
        $this->assertNull(GameMatch::where('game_id', $game->id)->whereNotNull('neutral_venue_name')->first());
    }

    public function test_venue_page_renders(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user);

        $response = $this->actingAs($user)->get(route('game.government-venue', $game->id));
        $response->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Modules\Transfer\TransferWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Winter (January) transfer window: the dates resolve to the winter window,
 * the AI market signs players in January, deadline day gets its own
 * notification flavor and rumor posts land on the social feed.
 */
class WinterTransferWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_january_resolves_to_the_winter_window(): void
    {
        $this->assertSame(TransferWindowType::WINTER, TransferWindowType::fromMonth(1));
        $this->assertSame(TransferWindowType::WINTER, TransferWindowType::fromDate(Carbon::parse('2027-01-15')));
        $this->assertSame([1], TransferWindowType::WINTER->months());
        $this->assertSame(2, TransferWindowType::WINTER->closeMonth());

        $window = new TransferWindow(Carbon::parse('2027-01-15'));

        $this->assertTrue($window->isOpen());
        $this->assertTrue($window->isWinter());
        $this->assertFalse($window->isSummer());
    }

    public function test_game_reports_winter_window_open_in_january(): void
    {
        $game = Game::factory()->create(['current_date' => '2027-01-10']);

        $this->assertTrue($game->isWinterWindowOpen());
        $this->assertTrue($game->isTransferWindowOpen());
        $this->assertFalse($game->isSummerWindowOpen());
    }

    public function test_ai_signs_players_during_the_winter_window(): void
    {
        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $aiTeam = Team::factory()->create(['name' => 'AI Team', 'country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2027-01-10',
        ]);

        // The AI club needs a continental reputation so the tier gate lets it
        // sign the tier-3 free agents (a local club is capped at tier 2).
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $aiTeam->id,
            'reputation_level' => ClubProfile::REPUTATION_CONTINENTAL,
            'base_reputation_level' => ClubProfile::REPUTATION_CONTINENTAL,
            'reputation_points' => 0,
        ]);

        // AI roster with room to sign: 20 players, thin up front.
        $positions = array_merge(
            array_fill(0, 3, 'Goalkeeper'),
            array_fill(0, 7, 'Centre-Back'),
            array_fill(0, 8, 'Central Midfield'),
            array_fill(0, 2, 'Centre-Forward'),
        );

        foreach ($positions as $position) {
            GamePlayer::factory()->forGame($game)->forTeam($aiTeam)->create([
                'position' => $position,
                'overall_score' => 68,
                'market_value_cents' => 500_000_00,
                'annual_wage' => 100_000_00,
            ]);
        }

        // Free-agent pool: more than the 15 the engine preserves for the user.
        GamePlayer::factory()->forGame($game)->count(20)->create([
            'team_id' => null,
            'position' => 'Centre-Forward',
            'overall_score' => 70,
            'market_value_cents' => 500_000_00,
            'annual_wage' => 100_000_00,
        ]);

        app(AITransferMarketService::class)->processWindowClose($game, TransferWindowType::WINTER->value);

        $winterMoves = GameTransfer::where('game_id', $game->id)
            ->where('window', TransferWindowType::WINTER->value)
            ->count();

        $this->assertGreaterThan(0, $winterMoves, 'The AI should sign players in the January window.');
    }

    public function test_winter_closing_notification_uses_deadline_day_flavor(): void
    {
        app()->setLocale('es');

        $game = Game::factory()->create(['current_date' => '2027-01-28']);

        $notification = app(NotificationService::class)
            ->notifyTransferWindowClosing($game, TransferWindowType::WINTER->value);

        $this->assertStringContainsString('Día límite', $notification->title);
        $this->assertSame('winter', $notification->metadata['window']);

        // Summer keeps the generic flavor.
        $summer = app(NotificationService::class)
            ->notifyTransferWindowClosing($game, TransferWindowType::SUMMER->value);

        $this->assertStringNotContainsString('Día límite', $summer->title);
    }

    public function test_deadline_day_rumors_are_posted_for_pending_bids(): void
    {
        app()->setLocale('es');

        $game = $this->januaryGameWithPendingBid('2027-01-28');

        app(SocialMediaService::class)->generateDeadlineDayRumors($game);

        $query = SocialPost::where('game_id', $game->id)->where('context', 'deadline_rumor');

        $this->assertTrue($query->exists(), 'A rumor post should appear during deadline week.');

        // Deduplicated: a second call posts nothing new.
        app(SocialMediaService::class)->generateDeadlineDayRumors($game);

        $this->assertSame(1, $query->count());
    }

    public function test_no_rumors_outside_deadline_week(): void
    {
        $game = $this->januaryGameWithPendingBid('2027-01-10');

        app(SocialMediaService::class)->generateDeadlineDayRumors($game);

        $this->assertFalse(
            SocialPost::where('game_id', $game->id)->where('context', 'deadline_rumor')->exists(),
            'No rumor posts before the final week of January.'
        );
    }

    public function test_schedule_friendly_page_shows_organize_stage_button(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $nationalTeam = Team::factory()->create([
            'name' => 'España',
            'country' => 'ES',
            'type' => 'national',
        ]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $nationalTeam->id,
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-09-01',
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);

        $response = $this->actingAs($user)->get(route('game.schedule-friendly', $game->id));

        $response->assertOk();
        $response->assertSee('Organizar stage');
        $response->assertSee('stage-configurator', false);
        $response->assertSee('stage-form', false);
    }

    /**
     * Build a January game whose user team has a player with a pending
     * unsolicited bid from another club.
     */
    private function januaryGameWithPendingBid(string $date): Game
    {
        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $bidder = Team::factory()->create(['name' => 'Rival Club', 'country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'country' => 'ES',
            'season' => '2026',
            'current_date' => $date,
        ]);

        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create([
            'name' => 'Test Star',
            'position' => 'Centre-Forward',
            'overall_score' => 82,
        ]);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $bidder->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'transfer_fee' => 2_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => Carbon::parse($date)->addDays(10),
            'game_date' => Carbon::parse($date),
        ]);

        return $game;
    }
}

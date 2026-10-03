<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Season\Services\TrainingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * BAJA sql-raw: varios métodos interpolaban IDs/valores en SQL crudo.
 * Ahora usan bindings sin cambiar la semántica.
 */
class SqlRawBindingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_bulk_update_growth_with_bindings(): void
    {
        $a = AcademyPlayer::create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => 'Canterana A',
            'nationality' => ['Spain'],
            'date_of_birth' => '2008-01-01',
            'position' => 'Central Midfield',
            'overall_score' => 50,
            'potential' => 85,
            'potential_low' => 80,
            'potential_high' => 90,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => false,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 50,
            'growth_progress' => 0.0,
        ]);
        $b = AcademyPlayer::create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => 'Canterana B',
            'nationality' => ['Spain'],
            'date_of_birth' => '2009-06-15',
            'position' => 'Goalkeeper',
            'overall_score' => 60,
            'potential' => 88,
            'potential_low' => 82,
            'potential_high' => 93,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => false,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 60,
            'growth_progress' => 0.0,
        ]);

        $method = new ReflectionMethod(YouthAcademyService::class, 'bulkUpdateGrowth');
        $method->setAccessible(true);
        $method->invoke(app(YouthAcademyService::class), [
            $a->id => ['overall_score' => 52, 'growth_progress' => 0.123456],
            $b->id => ['overall_score' => 61, 'growth_progress' => 0.5],
        ]);

        $this->assertSame(52, $a->fresh()->overall_score);
        $this->assertSame(61, $b->fresh()->overall_score);
        $this->assertEqualsWithDelta(0.123456, (float) $a->fresh()->growth_progress, 0.000001);
        $this->assertEqualsWithDelta(0.5, (float) $b->fresh()->growth_progress, 0.000001);
    }

    public function test_training_stage_squad_effects_with_bindings(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->team)->create();
        // La factory ya crea la fila de match_state: la ajustamos.
        GamePlayerMatchState::where('game_player_id', $player->id)->update([
            'fitness' => 80,
            'morale' => 70,
        ]);

        $method = new ReflectionMethod(TrainingStageService::class, 'applySquadEffects');
        $method->setAccessible(true);
        $method->invoke(
            app(TrainingStageService::class),
            $this->game,
            ['fitness' => 5, 'morale' => -10, 'injury_risk' => 0, 'youth_boost' => 0]
        );

        $state = GamePlayerMatchState::find($player->id);
        $this->assertSame(85, (int) $state->fitness);
        $this->assertSame(60, (int) $state->morale);
    }

    public function test_bulk_update_after_matches_with_bindings(): void
    {
        \App\Models\Competition::factory()->league()->create(['id' => 'ESP1']);
        $opponent = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $home = GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'team_id' => $this->team->id,
            'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
            'form' => '',
        ]);
        $away = GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'team_id' => $opponent->id,
            'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
            'form' => '',
        ]);

        app(StandingsCalculator::class)->bulkUpdateAfterMatches(
            $this->game->id,
            'ESP1',
            [[
                'homeTeamId' => $this->team->id,
                'awayTeamId' => $opponent->id,
                'homeScore' => 2,
                'awayScore' => 1,
            ]]
        );

        $homeFresh = $home->fresh();
        $awayFresh = $away->fresh();

        $this->assertSame(1, $homeFresh->played);
        $this->assertSame(1, $homeFresh->won);
        $this->assertSame(3, $homeFresh->points);
        $this->assertSame(2, $homeFresh->goals_for);
        $this->assertSame(1, $homeFresh->goals_against);
        $this->assertSame('W', $homeFresh->form);

        $this->assertSame(1, $awayFresh->played);
        $this->assertSame(1, $awayFresh->lost);
        $this->assertSame(0, $awayFresh->points);
        $this->assertSame('L', $awayFresh->form);
    }

    public function test_admin_users_search_treats_percent_literally(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['name' => 'alice%special', 'email' => 'alice1@example.com']);
        User::factory()->create(['name' => 'aliceXspecial', 'email' => 'alice2@example.com']);

        $response = $this->actingAs($admin)->get('/admin/users?search=' . urlencode('alice%special'));

        $response->assertOk();
        $users = $response->viewData('users');
        $names = collect($users->items())->pluck('name')->all();
        $this->assertContains('alice%special', $names);
        $this->assertNotContains('aliceXspecial', $names);
    }

    public function test_admin_waitlist_search_treats_underscore_literally(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        \App\Models\WaitlistEntry::create(['name' => 'mar_a test', 'email' => 'mara@example.com']);
        \App\Models\WaitlistEntry::create(['name' => 'marXa test', 'email' => 'marxa@example.com']);

        $response = $this->actingAs($admin)->get('/admin/waitlist?search=' . urlencode('mar_a'));

        $response->assertOk();
        $entries = $response->viewData('entries');
        $names = collect($entries->items())->pluck('name')->all();
        $this->assertContains('mar_a test', $names);
        $this->assertNotContains('marXa test', $names);
    }
}

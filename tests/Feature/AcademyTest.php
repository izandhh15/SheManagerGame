<?php

namespace Tests\Feature;

use App\Http\Actions\PromoteAcademyPlayer;
use App\Models\AcademyPlayer;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Models\UserSquadCareerRecord;
use App\Modules\Academy\Services\YouthAcademyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'Test Academy FC', 'country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-08-15',
            'reserve_team_id' => null,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'youth_academy_tier' => 3,
        ]);
    }

    public function test_season_batch_can_generate_a_jewel_of_the_academy(): void
    {
        $service = app(YouthAcademyService::class);

        $prospects = $service->generateSeasonBatch($this->game, forceJewel: true);

        $jewel = $prospects->first(fn ($p) => $p instanceof AcademyPlayer && $p->is_jewel);

        $this->assertNotNull($jewel, 'A forced jewel should be generated in the season batch');
        $this->assertTrue($jewel->is_jewel);
        $this->assertEquals(16, $jewel->age);
        $this->assertGreaterThanOrEqual(78, $jewel->potential);
        $this->assertLessThanOrEqual(92, $jewel->potential);
        $this->assertGreaterThanOrEqual(58, $jewel->overall_score);
        $this->assertNotEmpty($jewel->name);
        $this->assertNotEmpty($jewel->position);

        $this->assertDatabaseHas('academy_players', [
            'id' => $jewel->id,
            'is_jewel' => true,
        ]);
    }

    public function test_jewel_chance_grows_with_academy_tier(): void
    {
        $this->assertEquals(10, YouthAcademyService::getJewelChance(0));
        $this->assertEquals(20, YouthAcademyService::getJewelChance(1));
        $this->assertEquals(35, YouthAcademyService::getJewelChance(2));
        $this->assertEquals(50, YouthAcademyService::getJewelChance(3));
        $this->assertEquals(65, YouthAcademyService::getJewelChance(4));
        // Unknown tiers fall back to the tier-0 chance
        $this->assertEquals(10, YouthAcademyService::getJewelChance(99));
    }

    public function test_generate_jewel_creates_sixteen_year_old_with_elite_potential(): void
    {
        $service = app(YouthAcademyService::class);

        $jewel = $service->generateJewel($this->game);

        $this->assertInstanceOf(AcademyPlayer::class, $jewel);
        $this->assertTrue($jewel->is_jewel);
        $this->assertEquals(16, $jewel->age);
        $this->assertGreaterThanOrEqual(78, $jewel->potential);
        $this->assertLessThanOrEqual(92, $jewel->potential);
        // Potential range must be consistent
        $this->assertGreaterThanOrEqual($jewel->potential_low, $jewel->potential);
        $this->assertLessThanOrEqual($jewel->potential_high, $jewel->potential);
        $this->assertEquals($this->game->id, $jewel->game_id);
        $this->assertEquals($this->team->id, $jewel->team_id);
    }

    public function test_user_can_promote_an_academy_player_to_the_first_team(): void
    {
        $academyPlayer = AcademyPlayer::create([
            'id' => (string) Str::uuid(),
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => 'Joya de Prueba',
            'nationality' => ['Spain'],
            'date_of_birth' => Carbon::parse('2026-08-15')->subYears(16)->toDateString(),
            'position' => 'Centre-Forward',
            'overall_score' => 66,
            'potential' => 85,
            'potential_low' => 80,
            'potential_high' => 90,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => false,
            'is_jewel' => true,
            'joined_season' => 2026,
            'initial_overall' => 66,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('game.academy.promote', [$this->game->id, $academyPlayer->id]));

        $response->assertRedirect(route('game.squad.academy', $this->game->id));

        // The academy row is gone…
        $this->assertDatabaseMissing('academy_players', ['id' => $academyPlayer->id]);

        // …and the player now plays for the first team, flagged as cantera.
        $gamePlayer = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->team->id)
            ->where('name', 'Joya de Prueba')
            ->first();

        $this->assertNotNull($gamePlayer);
        $this->assertEquals('Centre-Forward', $gamePlayer->position);
        $this->assertNotNull($gamePlayer->number);

        $this->assertDatabaseHas('user_squad_career_records', [
            'game_id' => $this->game->id,
            'joined_from' => UserSquadCareerRecord::ORIGIN_ACADEMY,
        ]);
    }

    public function test_cannot_promote_another_teams_academy_player(): void
    {
        $otherTeam = Team::factory()->create(['name' => 'Rival FC', 'country' => 'ES']);
        $otherGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $otherTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $academyPlayer = AcademyPlayer::create([
            'id' => (string) Str::uuid(),
            'game_id' => $otherGame->id,
            'team_id' => $otherTeam->id,
            'name' => 'Canterana Rival',
            'nationality' => ['Spain'],
            'date_of_birth' => Carbon::parse('2026-08-15')->subYears(17)->toDateString(),
            'position' => 'Central Midfield',
            'overall_score' => 55,
            'potential' => 70,
            'potential_low' => 65,
            'potential_high' => 75,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => false,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 55,
        ]);

        // Attempting to promote it through THIS game's route must 404.
        $response = $this->actingAs($this->user)
            ->post(route('game.academy.promote', [$this->game->id, $academyPlayer->id]));

        $response->assertNotFound();
        $this->assertDatabaseHas('academy_players', ['id' => $academyPlayer->id]);
    }
}

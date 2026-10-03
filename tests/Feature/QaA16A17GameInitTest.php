<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\NationalSquadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QA FASE 2 (ALTOS): A16 + A17 — creación de partidas.
 *
 * A16: crear una partida de selección se bloqueaba cuando ya existían 2+
 * partidas de esa nación (de cualquier usuario): el leftJoin sin acotar de
 * NationalSquadService::provisionalSquad() con game_players duplicaba
 * player_ids (23 filas con ~13 únicos) y la validación "convoca exactamente
 * 23" fallaba. También provocaba que una retirada (retired_from_national)
 * en la partida de UN usuario excluyera a la jugadora de la convocatoria
 * provisional de TODOS los usuarios.
 *
 * A17: una carrera con team_id de una selección daba 500 (copy() on null en
 * PlayerGeneratorService) y dejaba la partida brickeada
 * (setup_completed_at null para siempre: pantalla de carga eterna).
 * InitGame ahora rechaza el team_id con un error claro ANTES de crear nada.
 *
 * Run: DB_DATABASE=virtua_fc_altos_w2_5 DB_USERNAME=virtua_fc DB_PASSWORD=virtua_fc \
 *      ~/workspace/.tools/frankenphp php-cli vendor/bin/phpunit tests/Feature/QaA16A17GameInitTest.php
 */
class QaA16A17GameInitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ---------- helpers ----------

    private function user(): User
    {
        return User::factory()->create(['has_career_access' => true]);
    }

    private function nationalTeam(string $name = 'Testlandia'): Team
    {
        return Team::create([
            'name' => $name,
            'type' => 'national',
            'country' => 'ES',
            'fifa_code' => 'TST',
            'is_placeholder' => false,
        ]);
    }

    private function club(string $name = 'Test FC'): Team
    {
        return Team::create([
            'name' => $name,
            'type' => 'club',
            'country' => 'ES',
            'is_placeholder' => false,
        ]);
    }

    /** $count templates for the nation with overall_score 99, 98, ... */
    private function seedTemplates(Team $team, int $count = 30): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $pid = (string) Str::uuid();
            DB::table('game_player_templates')->insert([
                'season' => NationalSquadService::TEMPLATE_SEASON,
                'player_id' => $pid,
                'team_id' => $team->id,
                'position' => 'MED',
                'overall_score' => 99 - $i,
                'name' => "Test Player {$i}",
            ]);
            $ids[] = $pid;
        }

        return $ids;
    }

    private function makeGame(User $user, Team $team): string
    {
        // games.competition_id is a real FK: seed a minimal competition.
        DB::table('competitions')->insertOrIgnore([
            'id' => 'WQC-UEFA',
            'name' => 'Test Qualifiers',
            'country' => 'ES',
        ]);

        $id = (string) Str::uuid();
        DB::table('games')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'WQC-UEFA',
            'base_season' => '2026',
            'game_mode' => 'tournament',
        ]);

        return $id;
    }

    private function addGamePlayers(string $gameId, Team $team, array $playerIds, bool $retired = false): void
    {
        foreach ($playerIds as $pid) {
            DB::table('game_players')->insert([
                'id' => (string) Str::uuid(),
                'game_id' => $gameId,
                'player_id' => $pid,
                'team_id' => $team->id,
                'position' => 'MED',
                'retired_from_national' => $retired,
            ]);
        }
    }

    // ---------- A16 ----------

    public function test_a16_provisional_squad_returns_23_unique_players_with_multiple_saves_of_the_nation(): void
    {
        $team = $this->nationalTeam();
        $templateIds = $this->seedTemplates($team, 30);

        // Two existing saves of the same nation (different users): with the
        // old unscoped leftJoin every template row joined to 2
        // game_players rows, so pluck()->limit(23) returned 23 rows with
        // only ~12 unique player_ids and creation failed the "exactly 23"
        // check.
        $userA = $this->user();
        $userB = $this->user();
        $this->addGamePlayers($this->makeGame($userA, $team), $team, array_slice($templateIds, 0, 23));
        $this->addGamePlayers($this->makeGame($userB, $team), $team, array_slice($templateIds, 0, 23));

        $squad = NationalSquadService::provisionalSquad($team->id);

        $this->assertCount(23, $squad, 'A16: provisional squad must hold 23 players');
        $this->assertCount(23, array_unique($squad), 'A16: provisional squad player_ids must be unique');
        $this->assertSame(array_slice($templateIds, 0, 23), $squad, 'A16: still the 23 highest-rated, in order');
    }

    public function test_a16_retirement_only_excludes_the_player_for_the_same_user(): void
    {
        $team = $this->nationalTeam();
        $templateIds = $this->seedTemplates($team, 30);
        $retiredId = $templateIds[0]; // top-rated player retires

        $userA = $this->user();
        $userB = $this->user();
        $this->addGamePlayers($this->makeGame($userA, $team), $team, [$retiredId], retired: true);
        $this->addGamePlayers($this->makeGame($userB, $team), $team, [$retiredId], retired: false);

        // For user A (whose save holds the retirement) she is excluded...
        $squadA = NationalSquadService::provisionalSquad($team->id, [], $userA->id);
        $this->assertCount(23, $squadA);
        $this->assertNotContains($retiredId, $squadA, 'A16: retired player must stay out for the user whose save retired her');

        // ...but user B is unaffected (previously she was excluded for EVERYONE).
        $squadB = NationalSquadService::provisionalSquad($team->id, [], $userB->id);
        $this->assertContains($retiredId, $squadB, 'A16: a retirement in another user\'s save must not exclude the player');
    }

    // ---------- A17 ----------

    public function test_a17_career_with_national_team_id_is_rejected_without_creating_a_game(): void
    {
        $user = $this->user();
        $team = $this->nationalTeam();

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => 'career',
            'team_id' => $team->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['team_id']);
        $this->assertSame(0, Game::where('user_id', $user->id)->count(), 'A17: no game may be created for a national team_id');
    }

    public function test_a17_career_with_unknown_team_id_is_rejected_without_creating_a_game(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => 'career',
            'team_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['team_id']);
        $this->assertSame(0, Game::where('user_id', $user->id)->count());
    }

    public function test_a17_career_with_club_team_id_passes_validation_and_reaches_creation(): void
    {
        $user = $this->user();
        $club = $this->club();

        $game = new Game();
        $game->id = (string) Str::uuid();

        $this->mock(GameCreationService::class, function ($mock) use ($user, $club, $game) {
            $mock->shouldReceive('create')
                ->once()
                ->with((string) $user->id, $club->id, 'career')
                ->andReturn($game);
        });
        // The game is a non-persisted stub: activation_events.game_id is a
        // real FK, so don't let the tracker insert against it.
        $this->mock(ActivationTracker::class, function ($mock) {
            $mock->shouldReceive('record')->once();
        });

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => 'career',
            'team_id' => $club->id,
        ]);

        $response->assertRedirect(route('game.welcome', $game->id));
        $response->assertSessionHasNoErrors();
    }

    public function test_a17_academy_career_with_national_team_id_is_rejected(): void
    {
        $user = $this->user();
        $team = $this->nationalTeam();

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => 'career_pro',
            'academy_club_id' => $team->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['academy_club_id']);
        $this->assertSame(0, Game::where('user_id', $user->id)->count(), 'A17: no game may be created for a national academy_club_id');
    }
}

<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\AcademyPlayer;
use App\Models\Competition;
use App\Models\Friendship;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Models\Team;
use App\Models\User;
use App\Modules\Social\Services\FriendshipService;
use App\Modules\Squad\Services\EligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FASE 4 (bugs BAJOS) — Agente C: B7, B8, B20 (B21 es cosmético, sin test).
 */
class AgentCFixesTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // B7 — PromoteAcademyPlayer debe bloquear canteranas cedidas
    // ------------------------------------------------------------------

    public function test_b7_promote_loaned_player_returns_404_and_keeps_loan(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'QA B7 FC', 'country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);

        $academy = AcademyPlayer::create([
            'id' => (string) Str::uuid(),
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Canterana Cedida QA',
            'nationality' => ['Spain'],
            'date_of_birth' => Carbon::parse('2026-08-15')->subYears(17)->toDateString(),
            'position' => 'Central Midfield',
            'overall_score' => 60,
            'potential' => 80,
            'potential_low' => 75,
            'potential_high' => 85,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => true,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 60,
        ]);

        $resp = $this->actingAs($user)->post("/game/{$game->id}/academy/{$academy->id}/promote");

        $resp->assertNotFound();

        // La cesión sigue intacta: la fila de academia no se promocionó.
        $this->assertDatabaseHas('academy_players', [
            'id' => $academy->id,
            'is_on_loan' => true,
        ]);
        $this->assertDatabaseCount('game_players', 0);
    }

    public function test_b7_promote_non_loaned_player_still_works(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'QA B7b FC', 'country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);

        $academy = AcademyPlayer::create([
            'id' => (string) Str::uuid(),
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Canterana Activa QA',
            'nationality' => ['Spain'],
            'date_of_birth' => Carbon::parse('2026-08-15')->subYears(17)->toDateString(),
            'position' => 'Central Midfield',
            'overall_score' => 60,
            'potential' => 80,
            'potential_low' => 75,
            'potential_high' => 85,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => false,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 60,
        ]);

        $resp = $this->actingAs($user)->post("/game/{$game->id}/academy/{$academy->id}/promote");

        $resp->assertRedirect();
        $this->assertDatabaseMissing('academy_players', ['id' => $academy->id]);
        $this->assertDatabaseCount('game_players', 1);
    }

    // ------------------------------------------------------------------
    // B8 — Las sanciones nuevas deben ACUMULARSE a la existente
    // ------------------------------------------------------------------

    public function test_b8_new_suspension_accumulates_on_existing(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'QA B8 FC', 'country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP3',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
        ]);

        $eligibility = app(EligibilityService::class);

        // Sanción de 2 partidos (p.ej. 10ª amarilla) y luego roja directa (1 partido).
        $eligibility->applySuspension($player, 2, 'ESP3');
        $eligibility->processRedCard($player, false, 'ESP3');

        $this->assertSame(3, PlayerSuspension::getMatchesRemaining($player->id, 'ESP3'));
    }

    public function test_b8_first_suspension_still_sets_value(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'QA B8b FC', 'country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP3',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
        ]);

        app(EligibilityService::class)->applySuspension($player, 2, 'ESP3');

        $this->assertSame(2, PlayerSuspension::getMatchesRemaining($player->id, 'ESP3'));
    }

    // ------------------------------------------------------------------
    // B20 — Amistades duplicadas en sentido inverso
    // ------------------------------------------------------------------

    private function friendshipService(): FriendshipService
    {
        return $this->app->make(FriendshipService::class);
    }

    public function test_b20_accept_cleans_reverse_pending_row(): void
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $svc = $this->friendshipService();

        // Carrera de doble envío: dos filas pendientes en sentidos opuestos.
        $r1 = Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'status' => Friendship::STATUS_PENDING]);
        $r2 = Friendship::create(['user_id' => $b->id, 'friend_id' => $a->id, 'status' => Friendship::STATUS_PENDING]);

        $this->assertTrue($svc->accept($b, $r1->id)['ok']);

        // La fila aceptada sobrevive; la inversa pendiente se limpia.
        $this->assertSame(1, Friendship::count());
        $this->assertTrue(Friendship::find($r1->id)->isAccepted());

        // Aceptar la (ya eliminada) fila inversa es un not_found controlado.
        $second = $svc->accept($a, $r2->id);
        $this->assertFalse($second['ok']);

        $this->assertTrue($svc->areFriends($a->id, $b->id));
        $this->assertCount(1, $svc->friends($a));
    }

    public function test_b20_remove_deletes_both_directions(): void
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $svc = $this->friendshipService();

        // Dos filas aceptadas para la misma pareja (estado corrupto previo).
        $r1 = Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'status' => Friendship::STATUS_ACCEPTED]);
        Friendship::create(['user_id' => $b->id, 'friend_id' => $a->id, 'status' => Friendship::STATUS_ACCEPTED]);

        $this->assertTrue($svc->remove($a, $r1->id)['ok']);

        // No queda ninguna fila entre la pareja en ningún sentido.
        $this->assertSame(0, Friendship::count());
        $this->assertNull($svc->findBetween($a->id, $b->id));
        $this->assertFalse($svc->areFriends($a->id, $b->id));
    }
}

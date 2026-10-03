<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA fase 2 — duplicados en redes sociales del club (A9, A10, A12).
 *
 * - A9: anunciar venta de entradas o descuento no debe bloquear para siempre
 *   la campaña de abonos (el chequeo debe filtrar por post_kind).
 * - A10: el chequeo de duplicados de jugadora debe filtrar por post_kind:
 *   un fichaje no puede bloquear el parte médico ni la venta de la misma
 *   jugadora, aunque el duplicado real del mismo tipo sí se bloquea.
 * - A12: anunciar fichaje/venta/lesión de una jugadora sin nombre (la columna
 *   `name` es nullable) no debe lanzar TypeError ni petar.
 */
class QaA9A10A12SocialDupesTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'CD Getafe Femenino', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP3',
            'country' => 'ES',
            'season' => '2026',
            'social_hype' => 0,
        ]);

        ClubProfile::create([
            'team_id' => $this->team->id,
            'reputation_level' => ClubProfile::REPUTATION_MODEST,
        ]);

        // Squad of average players (overall ~70), like the other club-social tests.
        for ($i = 0; $i < 5; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->team->id,
                'overall_score' => 70,
            ]);
        }
    }

    private function namedPlayer(string $name = 'Lucía Méndez'): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => $name,
            'overall_score' => 80,
        ]);
    }

    private function homeMatch(): GameMatch
    {
        return GameMatch::factory()->forGame($this->game)->create([
            'home_team_id' => $this->team->id,
            'competition_id' => 'ESP3',
            'scheduled_date' => now()->addDays(7),
            'played' => false,
        ]);
    }

    private function service(): ClubSocialService
    {
        return app(ClubSocialService::class);
    }

    // ------------------------------------------------------------------
    // A10 — el chequeo de duplicados de jugadora filtra por post_kind
    // ------------------------------------------------------------------

    public function test_a10_duplicate_announcement_of_same_type_is_still_blocked(): void
    {
        $player = $this->namedPlayer();

        $this->assertTrue($this->service()->announce($this->game, 'signing', $player->id)['ok']);

        $again = $this->service()->announce($this->game, 'signing', $player->id);
        $this->assertFalse($again['ok']);
        $this->assertSame(__('game.club_social_already_announced'), $again['message']);
    }

    public function test_a10_signing_does_not_block_injury_announcement(): void
    {
        $player = $this->namedPlayer();

        $signing = $this->service()->announce($this->game, 'signing', $player->id);
        $this->assertTrue($signing['ok']);

        $injury = $this->service()->announce($this->game, 'injury', $player->id, ['weeks' => 3]);
        $this->assertTrue($injury['ok']);

        $this->assertSame('signing', SocialPost::find($signing['post_id'])->post_kind);
        $this->assertSame('injury', SocialPost::find($injury['post_id'])->post_kind);
    }

    public function test_a10_signing_does_not_block_sale_announcement(): void
    {
        $player = $this->namedPlayer();

        $this->assertTrue($this->service()->announce($this->game, 'signing', $player->id)['ok']);

        $sale = $this->service()->announce($this->game, 'sale', $player->id);
        $this->assertTrue($sale['ok']);
    }

    public function test_a10_injury_does_not_block_sale_announcement(): void
    {
        $player = $this->namedPlayer();

        $this->assertTrue($this->service()->announce($this->game, 'injury', $player->id, ['weeks' => 2])['ok']);

        $sale = $this->service()->announce($this->game, 'sale', $player->id);
        $this->assertTrue($sale['ok']);
    }

    // ------------------------------------------------------------------
    // A9 — la campaña de abonos filtra por post_kind='season_tickets'
    // ------------------------------------------------------------------

    public function test_a9_ticket_sales_does_not_block_season_tickets(): void
    {
        $match = $this->homeMatch();

        $sales = $this->service()->announce($this->game, 'ticket_sales', null, ['match_id' => $match->id]);
        $this->assertTrue($sales['ok']);
        // The ticket_sales post does contain the 🎟️ emoji…
        $this->assertStringContainsString('🎟️', SocialPost::find($sales['post_id'])->text);

        // …but it must not block the season-ticket campaign.
        $tickets = $this->service()->announce($this->game, 'season_tickets');
        $this->assertTrue($tickets['ok']);
        $this->assertSame('season_tickets', SocialPost::find($tickets['post_id'])->post_kind);
    }

    public function test_a9_ticket_discount_does_not_block_season_tickets(): void
    {
        $match = $this->homeMatch();

        $discount = $this->service()->announce($this->game, 'ticket_discount', null, ['match_id' => $match->id]);
        $this->assertTrue($discount['ok']);
        $this->assertStringContainsString('🎟️', SocialPost::find($discount['post_id'])->text);

        $tickets = $this->service()->announce($this->game, 'season_tickets');
        $this->assertTrue($tickets['ok']);
    }

    public function test_a9_season_ticket_campaign_is_still_announced_only_once(): void
    {
        $match = $this->homeMatch();

        // Even with other 🎟️ posts around, the campaign itself stays one-per-game.
        $this->assertTrue($this->service()->announce($this->game, 'ticket_sales', null, ['match_id' => $match->id])['ok']);
        $this->assertTrue($this->service()->announce($this->game, 'season_tickets')['ok']);

        $again = $this->service()->announce($this->game, 'season_tickets');
        $this->assertFalse($again['ok']);
        $this->assertSame(__('game.club_social_already_announced'), $again['message']);
    }

    // ------------------------------------------------------------------
    // A12 — jugadora sin nombre: anunciar no lanza TypeError
    // ------------------------------------------------------------------

    public function test_a12_signing_sale_injury_with_null_player_name_do_not_throw(): void
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => null,
            'overall_score' => 80,
        ]);

        $signing = $this->service()->announce($this->game, 'signing', $player->id);
        $this->assertTrue($signing['ok']);

        $injury = $this->service()->announce($this->game, 'injury', $player->id, ['weeks' => 4]);
        $this->assertTrue($injury['ok']);

        $sale = $this->service()->announce($this->game, 'sale', $player->id);
        $this->assertTrue($sale['ok']);

        $this->assertNotNull(SocialPost::find($signing['post_id']));
        $this->assertNotNull(SocialPost::find($injury['post_id']));
        $this->assertNotNull(SocialPost::find($sale['post_id']));
    }

    public function test_a12_null_player_name_does_not_cause_false_duplicate_match(): void
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => null,
            'overall_score' => 80,
        ]);

        // Announcing for another player first must not poison the check for
        // the nameless player (a LIKE '%%' pattern would match everything).
        $other = $this->namedPlayer('Otra Jugadora');
        $this->assertTrue($this->service()->announce($this->game, 'signing', $other->id)['ok']);

        $signing = $this->service()->announce($this->game, 'signing', $player->id);
        $this->assertTrue($signing['ok']);
    }
}

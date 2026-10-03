<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R5 (fase 5, revisión línea a línea) — el anuncio type=renewal de las
 * redes del club:
 *
 * - NO puede extender el contrato gratis: exige una negociación de
 *   renovación completada (el flujo legítimo NegotiateRenewal ->
 *   ContractService::processRenewal() es quien extiende el contrato, con
 *   demanda salarial, salary cap y ledger).
 * - NO puede tocar jugadoras de rivales: announce() filtra por el equipo
 *   del usuario (scope userOwned).
 */
class R5RenewalRequiresNegotiationTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql'];

    private User $user;
    private Team $team;
    private Team $rival;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'CD Getafe Femenino', 'country' => 'ES']);
        $this->rival = Team::factory()->create(['name' => 'Rival FC Femenino', 'country' => 'ES']);
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
    }

    private function ownPlayer(string $name = 'Lucía Méndez'): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => $name,
            'overall_score' => 75,
            'contract_until' => '2027-06-30',
        ]);
    }

    private function rivalPlayer(): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->rival->id,
            'name' => 'Rival Star',
            'overall_score' => 90,
            'contract_until' => '2027-06-30',
        ]);
    }

    private function acceptNegotiation(GamePlayer $player): void
    {
        RenewalNegotiation::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'status' => RenewalNegotiation::STATUS_ACCEPTED,
        ]);
    }

    private function service(): ClubSocialService
    {
        return app(ClubSocialService::class);
    }

    public function test_renewal_announcement_without_negotiation_does_not_extend_contract(): void
    {
        $player = $this->ownPlayer();
        $before = $player->contract_until->toDateString();

        $result = $this->service()->announce($this->game, 'renewal', $player->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(__('game.club_social_renewal_needs_negotiation'), $result['message']);
        $this->assertSame($before, $player->fresh()->contract_until->toDateString());
        $this->assertSame(0, SocialPost::where('game_id', $this->game->id)->count());
    }

    public function test_renewal_announcement_with_completed_negotiation_publishes_without_touching_contract(): void
    {
        $player = $this->ownPlayer();
        $this->acceptNegotiation($player);
        // El flujo legítimo ya extendió el contrato al aceptar la negociación.
        $player->update(['contract_until' => '2029-06-30']);

        $result = $this->service()->announce($this->game, 'renewal', $player->id);

        $this->assertTrue($result['ok']);
        $this->assertSame('2029-06-30', $player->fresh()->contract_until->toDateString());
        $post = SocialPost::find($result['post_id']);
        $this->assertSame('renewal', $post->post_kind);
        $this->assertStringContainsString('2029', $post->text);
    }

    public function test_renewal_announcement_for_rival_player_is_rejected_and_untouched(): void
    {
        $rival = $this->rivalPlayer();
        // Incluso con una negociación aceptada, la jugadora de un rival no
        // es alcanzable: el filtro por equipo actúa primero.
        $this->acceptNegotiation($rival);
        $before = $rival->contract_until->toDateString();

        $result = $this->service()->announce($this->game, 'renewal', $rival->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(__('game.club_social_no_player'), $result['message']);
        $this->assertSame($before, $rival->fresh()->contract_until->toDateString());
        $this->assertSame(0, SocialPost::where('game_id', $this->game->id)->count());
    }
}

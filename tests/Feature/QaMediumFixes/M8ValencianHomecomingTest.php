<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M8: el texto valenciano del "vuelve a casa" mezclaba
 * español ("Vuelve al").
 *
 * Bug original: ClubSocialService::announceSigning(), rama 'va' del
 * homecoming, generaba "…torna a casa! Vuelve al Valencia CF Femenino.
 * Benvinguda de nou!…". (qa/bugs/agent-11.md BUG-4, agent-13.md)
 *
 * Fix: "Vuelve al" → "Torna al" en la rama 'va'.
 */
class M8ValencianHomecomingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fichar con el Valencia a una ex-jugadora no mete español en el
     * comunicado valenciano.
     *
     * Bug original: el texto contenía "Vuelve al Valencia CF Femenino".
     */
    public function test_valencian_homecoming_has_no_spanish(): void
    {
        app()->setLocale('es');

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $user = User::factory()->create();

        $team = Team::factory()->create(['name' => 'Valencia CF Femenino', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'social_hype' => 0,
        ]);
        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Xiqueta Que Torna',
            'overall_score' => 80,
            'contract_until' => '2029-06-30',
        ]);

        // Historial: Valencia -> otro club -> Valencia (vuelve a casa).
        $other = Team::factory()->create(['name' => 'Altre Club M8', 'country' => 'ES']);
        GameTransfer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'from_team_id' => $team->id,
            'to_team_id' => $other->id,
            'type' => 'transfer',
            'season' => '2025',
            'window' => 'summer',
        ]);
        GameTransfer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'from_team_id' => $other->id,
            'to_team_id' => $team->id,
            'type' => 'transfer',
            'season' => '2026',
            'window' => 'summer',
        ]);

        $result = app(ClubSocialService::class)->announce($game, 'signing', $player->id);
        $this->assertTrue($result['ok'], 'el anuncio debería publicarse');

        $text = SocialPost::find($result['post_id'])->text;
        $this->assertStringContainsString('#TornaACasa', $text, 'debería usar la rama homecoming valenciana');
        $this->assertStringContainsString('Torna al', $text);
        $this->assertStringNotContainsString('Vuelve al', $text, 'fragmento en español dentro del texto valenciano');
        $this->assertStringNotContainsString('vuelve a casa', $text);
    }
}

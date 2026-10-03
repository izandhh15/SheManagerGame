<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M7: anunciar una sede duplicada, o anunciar la sede de un
 * partido sin sede confirmada, devolvía ok=false con el mensaje de
 * ÉXITO "¡Comunicado publicado! La afición ya está comentando."
 *
 * Bug original: ClubSocialService::announceVenue() delegaba en
 * announceVenueConfirmed(), que devuelve null tanto en duplicado como
 * sin sede, y envolvía el null con __('game.club_social_published').
 * El wrapper announce() lo convertía en ['ok' => false] manteniendo el
 * texto de éxito: la action lo mostraba como flash de error con texto
 * de éxito. (qa/bugs/agent-11.md, BUG-3)
 *
 * Fix: announceVenue() distingue los dos casos — sin sede confirmada
 * usa la nueva clave game.club_social_no_venue; duplicado usa
 * game.club_social_already_announced.
 */
class M7VenueAnnounceMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $this->user = User::factory()->create();

        $team = Team::factory()->create(['name' => 'Real Madrid CF', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
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
        $this->game = $this->game->fresh('team');
    }

    private function homeMatch(array $overrides = []): GameMatch
    {
        $rival = Team::factory()->create(['name' => 'Rival M7 ' . uniqid(), 'country' => 'ES']);

        return GameMatch::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'home_team_id' => $this->game->team_id,
            'away_team_id' => $rival->id,
            'competition_id' => 'ESP1',
            'played' => false,
            'scheduled_date' => '2026-10-18',
            'stadium_name' => null,
            'neutral_venue_name' => null,
        ], $overrides));
    }

    /**
     * La sede duplicada informa "ya anunciado", nunca el texto de éxito.
     *
     * Bug original: el segundo announce devolvía
     * ['ok' => false, 'message' => '¡Comunicado publicado! ...'].
     */
    public function test_duplicate_venue_announce_reports_already_announced(): void
    {
        $match = $this->homeMatch(['stadium_name' => 'Estadio M7']);

        $first = app(ClubSocialService::class)
            ->announce($this->game, 'venue', null, ['match_id' => $match->id]);
        $this->assertTrue($first['ok'], 'el primer anuncio debería publicarse');

        $second = app(ClubSocialService::class)
            ->announce($this->game, 'venue', null, ['match_id' => $match->id]);
        $this->assertFalse($second['ok']);
        $this->assertSame('Ya hay un comunicado sobre esto.', $second['message']);
        $this->assertStringNotContainsString(
            '¡Comunicado publicado!',
            $second['message'],
            'un error nunca debe llevar el texto de éxito'
        );
    }

    /**
     * Un partido sin sede confirmada da un error claro, no texto de éxito.
     *
     * Bug original: devolvía ['ok' => false, 'message' => '¡Comunicado
     * publicado! ...'] aunque no se había publicado nada.
     */
    public function test_venue_announce_without_stadium_gives_clear_error(): void
    {
        $match = $this->homeMatch();

        $result = app(ClubSocialService::class)
            ->announce($this->game, 'venue', null, ['match_id' => $match->id]);

        $this->assertFalse($result['ok']);
        $this->assertSame('Ese partido aún no tiene sede confirmada.', $result['message']);
        $this->assertStringNotContainsString('¡Comunicado publicado!', $result['message']);
        $this->assertSame(
            0,
            SocialPost::where('game_id', $this->game->id)->where('context', 'club_official')->count(),
            'no se debe haber creado ningún post'
        );
    }
}

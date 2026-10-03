<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\ClubSocialService;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M20: el LIKE '%nombre%' del parte médico desbloqueaba la
 * lesión de otra jugadora — el parte publicado para "Ana María" generaba
 * diagnóstico para "Ana", sin comunicado oficial propio.
 * (qa/bugs/agent-16.md)
 *
 * Fix: match exacto por jugadora. Los comunicados nuevos guardan
 * game_player_id y la comprobación lo usa; los comunicados antiguos (sin
 * referencia) se reconocen con el nombre anclado al verbo de la plantilla
 * ("estará"/"estarà"/"will be"), que no casa con nombres más largos.
 */
class M20InjuryStatementExactMatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El parte de "Ana María" NO desbloquea la noticia de lesión de "Ana".
     */
    public function test_statement_for_similar_name_does_not_unlock_other_player(): void
    {
        [$game, $team] = $this->buildScenario();
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Ana',
            'injury_until' => Carbon::parse('2026-11-15'),
            'injury_type' => 'Ankle sprain',
        ]);

        // El comunicado es para OTRA jugadora ("Ana María"), sin referencia
        // de jugadora (formato anterior al fix, como en el test del QA).
        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $team->name,
            'author_handle' => '@testwfc',
            'text' => '🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: Ana María estará unas 4 semanas de baja. ¡Mucho ánimo, te esperamos! 💪',
            'context' => 'club_official',
        ]);

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);

        $this->assertNull(
            $this->findByCategory($articles, 'injury'),
            'el parte de "Ana María" no debe desbloquear la lesión de "Ana"'
        );
    }

    /**
     * El comunicado oficial para la jugadora SÍ desbloquea su parte médico,
     * con la referencia exacta guardada en el post.
     */
    public function test_official_statement_for_exact_player_unlocks_injury_news(): void
    {
        [$game, $team] = $this->buildScenario();
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Ana María',
            'injury_until' => Carbon::parse('2026-11-15'),
            'injury_type' => 'Ankle sprain',
        ]);

        $announced = app(ClubSocialService::class)
            ->announce($game, 'injury', $player->id, ['weeks' => 7]);
        $this->assertTrue($announced['ok'], 'el parte médico debería publicarse');

        $post = SocialPost::find($announced['post_id']);
        $this->assertSame($player->id, $post->game_player_id, 'el comunicado debe referenciar a la jugadora');

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $injury = $this->findByCategory($articles, 'injury');

        $this->assertNotNull($injury, 'con comunicado oficial sí hay noticia de lesión');
        $this->assertStringContainsString('Ana María', implode(' ', $injury->body));
        $this->assertStringContainsString('un esguince de tobillo', implode(' ', $injury->body));
    }

    /**
     * Compatibilidad hacia atrás: un comunicado antiguo (sin game_player_id)
     * con el nombre exacto sigue desbloqueando el parte.
     */
    public function test_legacy_statement_with_exact_name_still_unlocks(): void
    {
        [$game, $team] = $this->buildScenario();
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Ana',
            'injury_until' => Carbon::parse('2026-11-15'),
            'injury_type' => 'Ankle sprain',
        ]);

        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $team->name,
            'author_handle' => '@testwfc',
            'text' => '🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: Ana estará unas 4 semanas de baja. ¡Mucho ánimo, te esperamos! 💪',
            'context' => 'club_official',
        ]);

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $injury = $this->findByCategory($articles, 'injury');

        $this->assertNotNull($injury, 'el comunicado antiguo con el nombre exacto debe seguir valiendo');
        $this->assertStringContainsString('un esguince de tobillo', implode(' ', $injury->body));
    }

    /**
     * @return array{0:Game, 1:Team}
     */
    private function buildScenario(): array
    {
        app()->setLocale('es');

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $team];
    }

    private function findByCategory(array $articles, string $category): ?MatchNarrative
    {
        foreach ($articles as $article) {
            if ($article->category === $category) {
                return $article;
            }
        }

        return null;
    }
}

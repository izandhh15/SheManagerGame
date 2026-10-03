<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\PlayerSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión EXTRA: PlayerSocialService::postMatchReaction() tenía el
 * mismo patrón null que el bug A13 de la fase 2 (author_name null).
 *
 * Bug latente: postMatchReaction() usaba $player->name sin comprobarlo;
 * con una jugadora sin nombre usable (fila con name null o vacío)
 * creaba el SocialPost con 'author_name' null —columna NOT NULL— o con
 * un handle '@' inútil. El método hermano maybePostLifestyle() ya tenía
 * el guard ("No attributable author … Skip the post silently"); este no.
 *
 * Fix: mismo guard en postMatchReaction(): sin nombre atribuible no se
 * publica nada (nunca se inventa un nombre).
 */
class ExtraPostMatchReactionNullAuthorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una jugadora sin nombre no genera post ni excepción.
     *
     * Bug original: intentaba insertar author_name null (violación NOT
     * NULL) o publicaba un post sin autor atribuible.
     */
    public function test_nameless_best_player_produces_no_post(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Equipo EXTRA', 'country' => 'ES']);
        $rival = Team::factory()->create(['name' => 'Rival EXTRA', 'country' => 'ES']);
        $competition = Competition::factory()->league()->create(['id' => 'EXL', 'country' => 'ES', 'tier' => 1]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'country' => 'ES',
        ]);

        // La única jugadora del equipo no tiene nombre usable.
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => null,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'home_team_id' => $team->id,
            'away_team_id' => $rival->id,
            'played' => true,
            'home_score' => 2,
            'away_score' => 0,
            'scheduled_date' => '2026-10-03',
        ]);

        $post = app(PlayerSocialService::class)->postMatchReaction($game, $match);

        $this->assertNull($post, 'sin nombre atribuible no debe publicarse nada');
        $this->assertSame(
            0,
            SocialPost::where('game_id', $game->id)->where('context', 'player_reaction')->count()
        );
    }

    /**
     * Con nombre válido el comportamiento no cambia: se publica la
     * reacción con su autor.
     */
    public function test_named_player_still_posts_reaction(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Equipo EXTRA 2', 'country' => 'ES']);
        $rival = Team::factory()->create(['name' => 'Rival EXTRA 2', 'country' => 'ES']);
        $competition = Competition::factory()->league()->create(['id' => 'EXL2', 'country' => 'ES', 'tier' => 1]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'country' => 'ES',
        ]);

        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Jugadora Con Nombre',
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'home_team_id' => $team->id,
            'away_team_id' => $rival->id,
            'played' => true,
            'home_score' => 2,
            'away_score' => 0,
            'scheduled_date' => '2026-10-03',
        ]);

        $post = app(PlayerSocialService::class)->postMatchReaction($game, $match);

        $this->assertNotNull($post);
        $this->assertSame('Jugadora Con Nombre', $post->author_name);
    }
}

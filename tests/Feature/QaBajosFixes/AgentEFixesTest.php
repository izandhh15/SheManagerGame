<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\PlayerSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA bajos — Agente E: B14, B15, B19 (handles sociales + avatares).
 *
 * B14: jugadora con nombre solo-emoji ("🔥⚽💪") → el handle no puede
 *      quedarse en "@" pelado.
 * B15: nombre de 255 caracteres → el handle nunca supera 255 (varchar).
 * B19: iniciales de avatar con nombres multibyte ("Èric L.", "Àlex P.")
 *      → las vistas usan mb_substr(), no substr().
 */
class AgentEFixesTest extends TestCase
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
        app()->setLocale('es');

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
    }

    private function squadPlayer(array $attrs = []): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'overall_score' => 70,
        ], $attrs));
    }

    private function playedMatch(array $attrs = []): GameMatch
    {
        return GameMatch::factory()->forGame($this->game)->create(array_merge([
            'home_team_id' => $this->team->id,
            'played' => true,
            'home_score' => 2,
            'away_score' => 0,
        ], $attrs));
    }

    // ------------------------------------------------------------------
    // B14: nombre solo-emoji → handle saneado
    // ------------------------------------------------------------------

    public function test_emoji_only_player_name_generates_sane_handle(): void
    {
        $this->squadPlayer(['name' => '🔥⚽💪', 'overall_score' => 90]);
        $match = $this->playedMatch(['home_score' => 1]);

        $post = app(PlayerSocialService::class)->postMatchReaction($this->game->fresh(), $match);

        $this->assertNotNull($post, 'La reacción post-partido debería publicarse');
        $this->assertNotSame('@', $post->author_handle, 'El handle no puede quedarse en "@" pelado');
        $this->assertMatchesRegularExpression('/^@[a-z0-9_]+$/', $post->author_handle);
        $this->assertLessThanOrEqual(255, mb_strlen($post->author_handle));
    }

    public function test_emoji_only_player_handles_are_unique_per_player(): void
    {
        // Mismo nombre solo-emoji en dos jugadoras: el sufijo del id evita
        // que compartan handle.
        $p1 = $this->squadPlayer(['name' => '🔥⚽💪']);
        $p2 = $this->squadPlayer(['name' => '🔥⚽💪']);

        $method = new \ReflectionMethod(PlayerSocialService::class, 'playerHandle');
        $service = app(PlayerSocialService::class);

        $h1 = $method->invoke($service, $p1);
        $h2 = $method->invoke($service, $p2);

        $this->assertNotSame('@', $h1);
        $this->assertMatchesRegularExpression('/^@[a-z0-9_]+$/', $h1);
        $this->assertMatchesRegularExpression('/^@[a-z0-9_]+$/', $h2);
        $this->assertNotSame($h1, $h2, 'Dos jugadoras con el mismo nombre emoji no deben compartir handle');
    }

    // ------------------------------------------------------------------
    // B15: nombre de 255 caracteres → handle nunca > 255
    // ------------------------------------------------------------------

    public function test_player_reaction_with_huge_name_does_not_500(): void
    {
        // game_players.name es varchar(255): el máximo almacenable.
        // Sin truncar, el handle sería '@' + 255 chars = 256 → SQLSTATE 22001.
        $hugeName = str_repeat('a', 255);
        $this->squadPlayer(['name' => $hugeName, 'overall_score' => 90]);
        $match = $this->playedMatch();

        // Lanza QueryException si el handle rebasa el varchar(255).
        $post = app(PlayerSocialService::class)->postMatchReaction($this->game->fresh(), $match);

        if ($post !== null) {
            $this->assertLessThanOrEqual(255, mb_strlen($post->author_name));
            $this->assertLessThanOrEqual(255, mb_strlen($post->author_handle), 'author_handle supera el varchar(255)');
        }
    }

    public function test_player_lifestyle_with_huge_name_does_not_500(): void
    {
        $hugeName = str_repeat('b', 255);
        $this->squadPlayer(['name' => $hugeName]);

        $service = app(PlayerSocialService::class);
        $post = null;
        for ($i = 0; $i < 200 && $post === null; $i++) {
            $post = $service->maybePostLifestyle($this->game->fresh());
        }

        // Si sale el 12 % de probabilidad, el INSERT no debe reventar.
        if ($post !== null) {
            $this->assertLessThanOrEqual(255, mb_strlen($post->author_name), 'author_name supera el varchar(255)');
            $this->assertLessThanOrEqual(255, mb_strlen($post->author_handle), 'author_handle supera el varchar(255)');
        }
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // B19: iniciales de avatar multibyte
    // ------------------------------------------------------------------

    public function test_avatar_initial_logic_with_multibyte_names(): void
    {
        // Lo que hacen las vistas tras el fix: mb_substr($name, 0, 1).
        // Con substr() se cortaba el byte ("\xC3", UTF-8 inválido) y
        // htmlspecialchars (Blade {{ }}) devolvía cadena vacía.
        $expected = ['Èric L.' => 'È', 'Àlex P.' => 'À'];
        foreach ($expected as $name => $initial) {
            $this->assertSame($initial, mb_substr($name, 0, 1), "mb_substr() extrae la inicial de '{$name}'");
            $this->assertTrue(mb_check_encoding(mb_substr($name, 0, 1), 'UTF-8'));
            $this->assertSame($initial, htmlspecialchars(mb_substr($name, 0, 1), ENT_QUOTES, 'UTF-8'));
        }
    }

    public function test_avatar_views_use_mb_substr_for_initials(): void
    {
        // Guarda de regresión: ninguna de las 3 vistas puede volver a usar
        // substr() para la inicial del avatar.
        $files = [
            resource_path('views/social-feed.blade.php'),
            resource_path('views/club-social.blade.php'),
            resource_path('views/national-social.blade.php'),
        ];
        foreach ($files as $file) {
            $this->assertFileExists($file);
            $contents = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/\{\{\s*substr\(\$(post|reply)->author_name/',
                $contents,
                basename($file) . ' sigue usando substr() para la inicial del avatar'
            );
            $this->assertMatchesRegularExpression(
                '/\{\{\s*mb_substr\(\$(post|reply)->author_name,\s*0,\s*1\)\s*\}\}/',
                $contents,
                basename($file) . ' no usa mb_substr() para la inicial del avatar'
            );
        }
    }
}

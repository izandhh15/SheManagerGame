<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameJournalist;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\JournalistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R15 (fase 5, revisión línea a línea) — postTransferNews(string $playerName)
 * recibía $player->name (columna nullable) => TypeError => 500 al completar
 * traspasos. Ahora acepta ?string y publica con un genérico sin inventar
 * ningún nombre.
 */
class R15NullPlayerNameTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql'];

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('es');

        Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'CD Getafe Femenino', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP3',
            'country' => 'ES',
            'season' => '2026',
        ]);

        GameJournalist::create([
            'game_id' => $this->game->id,
            'name' => 'Lucía Ferrer',
            'handle' => '@luciaferrer',
            'specialty' => 'fichajes',
            'followers' => 50000,
            'active' => true,
        ]);
    }

    public function test_post_transfer_news_with_null_name_publishes_generic_fallback(): void
    {
        $post = app(JournalistService::class)->postTransferNews(
            $this->game,
            null,
            'Rival FC Femenino',
            'CD Getafe Femenino',
            'in',
        );

        $this->assertInstanceOf(SocialPost::class, $post);
        $this->assertStringContainsString('Una jugadora', $post->text);
        $this->assertStringContainsString('CD Getafe Femenino', $post->text);
    }

    public function test_post_transfer_news_with_null_name_out_kind(): void
    {
        $post = app(JournalistService::class)->postTransferNews(
            $this->game,
            null,
            'CD Getafe Femenino',
            'Rival FC Femenino',
            'out',
        );

        $this->assertInstanceOf(SocialPost::class, $post);
        $this->assertStringContainsString('Una jugadora', $post->text);
    }

    public function test_post_transfer_news_with_real_name_still_works(): void
    {
        $post = app(JournalistService::class)->postTransferNews(
            $this->game,
            'Alexia Prats',
            'Rival FC Femenino',
            'CD Getafe Femenino',
            'in',
        );

        $this->assertInstanceOf(SocialPost::class, $post);
        $this->assertStringContainsString('Alexia Prats', $post->text);
    }
}

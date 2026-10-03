<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PressStatement;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R11 (fase 5, revisión línea a línea) — player_id forjado en ruedas de
 * prensa: criticize_flop con una jugadora inexistente dejaba $targetRating
 * en null y `null <= 6.0 === true` regalaba +6 de confianza de la directiva,
 * farmeable en cada rueda de prensa (rompía la mecánica de destitución).
 *
 * El player debe existir en los ratings del partido; si no valida, la
 * declaración se rechaza sin efecto (sin statement, sin oleada de fans,
 * sin cambio de confianza, y el hueco de la rueda de prensa queda libre).
 */
class R11CriticizeFlopValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql'];

    private User $user;
    private Team $team;
    private Game $game;
    private GameMatch $match;

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
            'board_confidence' => 70,
        ]);

        // Medias bajas => las notas sintetizadas quedan siempre <= 6.0, así
        // que un criticize_flop legítimo otorga +6 de confianza.
        for ($i = 0; $i < 5; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->team->id,
                'overall_score' => 40,
            ]);
        }

        $this->match = GameMatch::factory()->forGame($this->game)->create([
            'home_team_id' => $this->team->id,
            'competition_id' => 'ESP3',
            'scheduled_date' => now()->subDay(),
            'played' => true,
            'home_score' => 0,
            'away_score' => 2,
        ]);
    }

    private function service(): SocialMediaService
    {
        return app(SocialMediaService::class);
    }

    private function worstPlayerId(): string
    {
        foreach ($this->service()->pressOptions($this->game, $this->match) as $option) {
            if ($option['key'] === 'criticize_flop') {
                return $option['player']['id'];
            }
        }

        $this->fail('criticize_flop option missing');
    }

    public function test_forged_player_id_gives_no_confidence_boost(): void
    {
        $statementsBefore = PressStatement::where('game_id', $this->game->id)->count();
        $postsBefore = SocialPost::where('game_id', $this->game->id)->count();

        $this->service()->makeStatement($this->game, $this->match, 'criticize_flop', 'jugadora-que-no-existe');

        $this->assertSame(70, $this->game->fresh()->board_confidence);
        $this->assertSame($statementsBefore, PressStatement::where('game_id', $this->game->id)->count());
        $this->assertSame($postsBefore, SocialPost::where('game_id', $this->game->id)->count());
    }

    public function test_criticize_flop_without_player_is_rejected(): void
    {
        $this->service()->makeStatement($this->game, $this->match, 'criticize_flop');

        $this->assertSame(70, $this->game->fresh()->board_confidence);
        $this->assertSame(0, PressStatement::where('game_id', $this->game->id)->count());
    }

    public function test_forged_player_id_on_team_statement_is_rejected_too(): void
    {
        $this->service()->makeStatement($this->game, $this->match, 'praise_team', 'jugadora-que-no-existe');

        $this->assertSame(70, $this->game->fresh()->board_confidence);
        $this->assertSame(0, PressStatement::where('game_id', $this->game->id)->count());
    }

    public function test_legitimate_criticize_flop_still_grants_confidence(): void
    {
        $this->service()->makeStatement($this->game, $this->match, 'criticize_flop', $this->worstPlayerId());

        // Nota <= 6.0 garantizada por la plantilla de media 40: +6.
        $this->assertSame(76, $this->game->fresh()->board_confidence);
        $this->assertSame(1, PressStatement::where('game_id', $this->game->id)->count());
        $this->assertGreaterThan(0, SocialPost::where('game_id', $this->game->id)->count());
    }
}

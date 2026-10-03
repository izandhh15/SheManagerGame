<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\GamePlayer;
use App\Models\PressStatement;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M15 (QA agent-15 / FEED-02): doble declaración para el mismo
 * partido → doble oleada de fans y doble impacto en confianza.
 *
 * Bug original: SubmitPressStatement protegía "una declaración por partido"
 * con check-then-insert (PressStatement::exists() y luego insert) sin
 * transacción/bloqueo, press_statements NO tenía constraint único en
 * (game_id, match_id) y makeStatement() tampoco se protegía. Dos
 * peticiones que pasaban el exists() a la vez generaban 12-20 posts y
 * aplicaban el impacto en board_confidence dos veces (+5+5 en vez de +5).
 *
 * Fix: (1) migración 2026_10_03_000039 añade unique(game_id, match_id) a
 * press_statements; (2) makeStatement() usa firstOrCreate + captura
 * UniqueConstraintViolationException: la segunda declaración reutiliza la
 * existente sin emitir oleada ni impacto de nuevo.
 *
 * Estos tests son el port (invertido) del repro del QA
 * (InternetFeedDoubleStatementTest).
 */
class M15DoubleStatementIdempotentTest extends TestCase
{
    use RefreshDatabase;

    private function pressFixture(): array
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = \App\Models\Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'current_date' => '2026-08-20',
            'board_confidence' => 70,
        ]);

        GamePlayer::factory()->count(14)->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'overall_score' => 75,
        ]);

        $match = \App\Models\GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id,
            'home_score' => 3,
            'away_score' => 0,
            'played' => true,
        ]);

        return [$game, $match];
    }

    /**
     * Dos llamadas a makeStatement() con el mismo partido (simula doble
     * clic / doble submit del formulario de rueda de prensa): solo una
     * declaración, una oleada y un impacto en confianza.
     */
    public function test_double_statement_same_match_is_idempotent(): void
    {
        [$game, $match] = $this->pressFixture();
        $svc = app(SocialMediaService::class);

        mt_srand(7);

        $svc->makeStatement($game, $match, 'praise_team');
        $svc->makeStatement($game, $match, 'praise_team');

        $statements = PressStatement::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->count();
        $this->assertSame(1, $statements, "M15: two press statements recorded for the same match ({$statements}).");

        $posts = SocialPost::where('game_id', $game->id)
            ->where('context', 'post_match')
            ->where('match_id', $match->id)
            ->count();
        // Una oleada son 6-10 posts; dos oleadas serían 12-20.
        $this->assertLessThanOrEqual(10, $posts, "M15: {$posts} fan posts for one press conference (two waves).");

        // praise_team tras victoria = +5, aplicado una sola vez.
        $this->assertSame(
            75,
            (int) $game->fresh()->board_confidence,
            'M15: board confidence moved twice for a single press conference.'
        );
    }

    /**
     * El constraint único de la migración bloquea un segundo insert directo
     * para el mismo (game_id, match_id).
     */
    public function test_unique_constraint_rejects_second_statement_row(): void
    {
        [$game, $match] = $this->pressFixture();

        PressStatement::create([
            'game_id' => $game->id,
            'match_id' => $match->id,
            'statement_key' => 'praise_team',
            'sentiment_impact' => 5,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        PressStatement::create([
            'game_id' => $game->id,
            'match_id' => $match->id,
            'statement_key' => 'criticize_team',
            'sentiment_impact' => -14,
        ]);
    }

    /**
     * Declarar tras otro partido distinto sigue funcionando con normalidad.
     */
    public function test_statement_for_different_match_still_works(): void
    {
        [$game, $match] = $this->pressFixture();
        $svc = app(SocialMediaService::class);

        mt_srand(7);
        $svc->makeStatement($game, $match, 'praise_team');

        $match2 = \App\Models\GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'away_team_id' => Team::factory()->create()->id,
            'home_score' => 2,
            'away_score' => 0,
            'played' => true,
        ]);
        $svc->makeStatement($game, $match2, 'praise_team');

        $this->assertSame(
            2,
            PressStatement::where('game_id', $game->id)->count(),
            'M15: statements for DIFFERENT matches must both be recorded.'
        );
        // +5 por cada declaración: 70 -> 75 -> 80.
        $this->assertSame(80, (int) $game->fresh()->board_confidence);
    }
}

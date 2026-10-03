<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug crítico C6 (fix en el commit 8583c98).
 *
 * En PHP 32-bit (Wasmer Edge, producción) crc32() devuelve un entero con
 * signo: la mitad de las semillas dan valores NEGATIVOS. PressNewsService
 * usaba `crc32(...) % count` como índice de Collection sin saneamiento:
 * índice -1 -> "Undefined array key" -> ErrorException -> 500 en el feed
 * de noticias. El fix sanea con `(crc32(...) & 0x7FFFFFFF) % count`
 * (PressNewsService:351 y :569; ManagerPressureService:85 y :123).
 *
 * En local (PHP 64-bit) crc32() nunca es negativo, así que el bug no se
 * reproduce de forma natural: estos tests emulan la semántica de 32 bits
 * con signed32() (técnica portada del QA de agent-16) y además verifican
 * el servicio real de punta a punta con rondas cuyo crc32 tendría el bit
 * alto activo en producción. Se usan 3 candidatas porque con count=2 el
 * bit 31 no afecta a `% 2` y el test no distinguiría fix de no-fix.
 *
 * Tests portados de PressNewsQaTest
 * (~/workspace/shemanager/qa/workers/agent-16/PressNewsQaTest.php):
 *   - test_injury_pick_index_is_safe_on_32bit_php
 *   - test_shortlist_pick_index_is_safe_on_32bit_php
 */
class C6Crc32IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_injury_pick_index_is_safe_on_32bit_php(): void
    {
        [$game, $team] = $this->buildScenario();

        // 3 lesionadas con injury_until distinto: injuryArticle() ordena por
        // fecha ascendente antes de elegir, así el orden es determinista.
        $injuredNames = ['Lesionada Alpha', 'Lesionada Beta', 'Lesionada Gamma'];
        foreach ($injuredNames as $i => $name) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
                'name' => $name,
                'injury_until' => Carbon::parse('2026-11-1' . $i),
                'injury_type' => 'Ankle sprain',
            ]);
            // Parte médico oficial para cada una: la noticia "injury" existe
            // sea cual sea la elegida (sin depender del coin flip del rumor).
            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => $team->name,
                'author_handle' => '@testwfc',
                'text' => "🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: {$name} estará unas 4 semanas de baja. ¡Mucho ánimo! 💪",
                'context' => 'club_official',
            ]);
        }

        // Ronda cuyo crc32 tendría el bit alto activo en PHP 32-bit.
        $round = $this->findNegativeCrcRound($game->id . 'injury%s');
        $this->assertNotNull($round, 'Debería existir una ronda con crc32 negativo en 5000 intentos.');

        $unsigned = crc32($game->id . 'injury' . $round);
        $signed = $this->signed32($unsigned);

        // Precondición: sin el fix, en PHP 32-bit este crc32 sería negativo
        // y el índice podría ser -1 -> 500.
        $this->assertLessThan(
            0, $signed,
            'La ronda elegida debe emular el escenario del bug (crc32 negativo en 32-bit).'
        );

        // El índice saneado (PressNewsService:351) es válido y coincide en
        // ambas plataformas: el fix no cambia el comportamiento en 64-bit.
        $fixedIndex = ($unsigned & 0x7FFFFFFF) % 3;
        $this->assertSame(
            $fixedIndex, ($signed & 0x7FFFFFFF) % 3,
            'El saneamiento debe dar el mismo índice en PHP 32-bit y 64-bit.'
        );
        $this->assertGreaterThanOrEqual(0, $fixedIndex);
        $this->assertLessThan(3, $fixedIndex);

        // Punta a punta: el servicio elige a la lesionada del índice saneado.
        $next = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'played' => false,
            'round_number' => $round,
            'scheduled_date' => Carbon::parse('2026-10-10'),
        ]);

        $articles = $this->service()->articles($game->refresh(), $next);
        $injury = $this->findByCategory($articles, 'injury');
        $this->assertNotNull($injury, 'Debe generarse la noticia de lesión sin lanzar excepción.');

        $this->assertStringContainsString(
            $injuredNames[$fixedIndex],
            $injury->headline,
            "El servicio debe elegir a la lesionada del índice saneado ({$fixedIndex}), no -1."
        );
    }

    public function test_shortlist_pick_index_is_safe_on_32bit_php(): void
    {
        [$game, , $opponent] = $this->buildScenario();

        $shortlistedNames = ['Seguido Alpha', 'Seguido Beta', 'Seguido Gamma'];
        foreach ($shortlistedNames as $name) {
            $p = GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $opponent->id,
                'name' => $name,
            ]);
            ShortlistedPlayer::create([
                'game_id' => $game->id,
                'game_player_id' => $p->id,
                'added_at' => Carbon::parse('2026-10-01'),
            ]);
        }

        // Ronda cuyo crc32 tendría el bit alto activo en PHP 32-bit.
        $round = $this->findNegativeCrcRound($game->id . 'shortlist%s');
        $this->assertNotNull($round, 'Debería existir una ronda con crc32 negativo en 5000 intentos.');

        $unsigned = crc32($game->id . 'shortlist' . $round);
        $signed = $this->signed32($unsigned);

        // Precondición: sin el fix, en PHP 32-bit este crc32 sería negativo
        // y el índice podría ser -1 -> 500.
        $this->assertLessThan(
            0, $signed,
            'La ronda elegida debe emular el escenario del bug (crc32 negativo en 32-bit).'
        );

        // El índice saneado (PressNewsService:569) es válido y coincide en
        // ambas plataformas.
        $fixedIndex = ($unsigned & 0x7FFFFFFF) % 3;
        $this->assertSame(
            $fixedIndex, ($signed & 0x7FFFFFFF) % 3,
            'El saneamiento debe dar el mismo índice en PHP 32-bit y 64-bit.'
        );
        $this->assertGreaterThanOrEqual(0, $fixedIndex);
        $this->assertLessThan(3, $fixedIndex);

        // Punta a punta: el servicio elige al seguido del índice saneado.
        $next = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $opponent->id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'played' => false,
            'round_number' => $round,
            'scheduled_date' => Carbon::parse('2026-10-10'),
        ]);

        $articles = $this->service()->articles($game->refresh(), $next);
        $rumor = $this->findByCategory($articles, 'signing_rumor');
        $this->assertNotNull($rumor, 'Debe generarse el rumor de fichaje (fallback de shortlist).');

        $this->assertStringContainsString(
            $shortlistedNames[$fixedIndex],
            $rumor->headline,
            "El servicio debe elegir al seguido del índice saneado ({$fixedIndex}), no -1."
        );
    }

    // ── Helpers (portados de PressNewsQaTest del agent-16) ───────────────

    /**
     * @return array{Game, Team, Team}
     */
    private function buildScenario(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $opponent = Team::factory()->create(['name' => 'Rival WFC', 'country' => 'ES']);

        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $team, $opponent];
    }

    /**
     * @param array<MatchNarrative> $articles
     */
    private function findByCategory(array $articles, string $category): ?MatchNarrative
    {
        foreach ($articles as $article) {
            if ($article->category === $category) {
                return $article;
            }
        }

        return null;
    }

    private function service(): PressNewsService
    {
        return app(PressNewsService::class);
    }

    /** Emula la interpretación con signo de crc32() en PHP de 32 bits. */
    private function signed32(int $unsigned): int
    {
        return $unsigned >= 2 ** 31 ? $unsigned - 2 ** 32 : $unsigned;
    }

    /** Busca una ronda cuyo crc32(semilla) sería negativo en PHP 32-bit. */
    private function findNegativeCrcRound(string $seedPattern): ?int
    {
        for ($r = 0; $r < 5000; $r++) {
            if (crc32(sprintf($seedPattern, $r)) >= 2 ** 31) {
                return $r;
            }
        }

        return null;
    }
}

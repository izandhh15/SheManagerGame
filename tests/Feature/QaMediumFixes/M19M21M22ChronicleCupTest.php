<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones M19 / M21 / M22: la crónica hablaba de "puntos" en
 * eliminatorias de copa e ignoraba la tanda de penaltis.
 *
 * - M19 (agent-16): victoria copera con "Tres puntos de oro".
 * - M21 (agent-17): empate copero con "Reparto de puntos".
 * - M22 (agent-17): final ganada en penaltis narrada como "empate".
 *
 * Fix: chronicleMeaningLine() distingue copa/liga y la crónica refleja la
 * tanda de penaltis (home_score_penalties/away_score_penalties) en el
 * resultado y en la línea de significado.
 */
class M19M21M22ChronicleCupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M19: la crónica de una victoria en eliminatoria no habla de puntos.
     */
    public function test_cup_win_chronicle_does_not_mention_points(): void
    {
        $chronicle = $this->chronicleFor([
            'home_score' => 2,
            'away_score' => 1,
        ], true);

        $text = implode(' ', $chronicle->body);
        $this->assertStringNotContainsStringIgnoringCase('puntos', $text);
        $this->assertStringNotContainsString('Tres puntos de oro', $text);
        $this->assertStringContainsStringIgnoringCase('eliminatoria', $text);
    }

    /**
     * M21: la crónica de un empate de copa no habla de reparto de puntos.
     */
    public function test_cup_draw_chronicle_does_not_talk_about_points(): void
    {
        $chronicle = $this->chronicleFor([
            'home_score' => 1,
            'away_score' => 1,
        ], true);

        $text = implode(' ', $chronicle->body);
        $this->assertStringNotContainsString('Reparto de puntos', $text);
        $this->assertStringNotContainsStringIgnoringCase('puntos', $text);
        $this->assertStringContainsStringIgnoringCase('eliminatoria', $text);
    }

    /**
     * M22: una final ganada en penaltis se narra como victoria en penaltis,
     * no como empate.
     */
    public function test_final_won_on_penalties_narrated_as_victory(): void
    {
        $chronicle = $this->chronicleFor([
            'home_score' => 1,
            'away_score' => 1,
            'home_score_penalties' => 4,
            'away_score_penalties' => 2,
        ], true);

        $this->assertStringContainsString('1-1', $chronicle->headline);
        $this->assertStringContainsStringIgnoringCase('penaltis', $chronicle->headline);

        $text = implode(' ', $chronicle->body);
        $this->assertStringContainsStringIgnoringCase('victoria', $chronicle->body[0]);
        $this->assertStringContainsStringIgnoringCase('penaltis', $text);
        $this->assertStringNotContainsString('Reparto de puntos', $text);
        $this->assertStringNotContainsStringIgnoringCase('empate', $chronicle->body[0]);
    }

    /**
     * M22 (caso inverso): una final perdida en penaltis se narra como
     * derrota, no como empate anodino.
     */
    public function test_final_lost_on_penalties_narrated_as_defeat(): void
    {
        [$game, $team, $opponent] = $this->buildScenario();

        $tie = CupTie::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'round_number' => 1,
            'home_team_id' => $opponent->id,
            'away_team_id' => $team->id,
        ]);

        // El usuario juega fuera y pierde la tanda.
        GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $opponent->id,
            'away_team_id' => $team->id,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_penalties' => 5,
            'away_score_penalties' => 3,
            'cup_tie_id' => $tie->id,
            'played' => true,
            'scheduled_date' => Carbon::parse('2026-09-20'),
            'round_number' => 5,
        ]);

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $chronicle = $this->findByCategory($articles, 'chronicle');
        $this->assertNotNull($chronicle);

        $text = implode(' ', $chronicle->body);
        $this->assertStringContainsStringIgnoringCase('derrota', $chronicle->body[0]);
        $this->assertStringContainsStringIgnoringCase('penaltis', $text);
        $this->assertStringNotContainsString('Reparto de puntos', $text);
    }

    /**
     * Guarda: la crónica de liga sigue hablando de puntos (la corrección
     * no debe pasarse de frenada).
     */
    public function test_league_win_chronicle_still_mentions_points(): void
    {
        $chronicle = $this->chronicleFor([
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $text = implode(' ', $chronicle->body);
        $this->assertStringContainsString('Tres puntos de oro', $text);
    }

    /**
     * Crea un partido jugado en casa del usuario y devuelve su crónica.
     */
    private function chronicleFor(array $matchOverrides, bool $cup = false): MatchNarrative
    {
        [$game, $team, $opponent] = $this->buildScenario();

        if ($cup) {
            $tie = CupTie::factory()->create([
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'round_number' => 1,
                'home_team_id' => $team->id,
                'away_team_id' => $opponent->id,
            ]);
            $matchOverrides['cup_tie_id'] = $tie->id;
        }

        GameMatch::factory()->forGame($game)->create(array_merge([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'played' => true,
            'scheduled_date' => Carbon::parse('2026-09-20'),
            'round_number' => 5,
        ], $matchOverrides));

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $chronicle = $this->findByCategory($articles, 'chronicle');
        $this->assertNotNull($chronicle, 'un partido jugado debe producir crónica');

        return $chronicle;
    }

    /**
     * @return array{0:Game, 1:Team, 2:Team}
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

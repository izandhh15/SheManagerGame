<?php

namespace Tests\Unit\Media;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\ManagerPressureService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Press rumour mill: teams on a bad run get full media articles
 * ("El entrenador del X podría ser cesado") with 3-4 one-line paragraphs.
 * Coach names are never invented.
 */
class ManagerPressureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bad_run_generates_pressure_article_with_one_line_paragraphs(): void
    {
        [$game, $team] = $this->buildScenario(results: ['L', 'L', 'L', 'L', 'L'], coachName: 'Test Coach');

        $articles = $this->service()->pressureArticles($game->refresh(), $this->nextMatch($game));

        $this->assertNotEmpty($articles, 'A 5-loss run must generate a pressure article.');
        $article = $this->findArticleFor($articles, $team->id);
        $this->assertNotNull($article);
        $this->assertSame('pressure', $article->category);
        $this->assertStringContainsString($team->name, $article->headline);
        $this->assertStringContainsString('Test Coach', $article->headline);
        $this->assertNotEmpty($article->source);

        $this->assertIsArray($article->body);
        $this->assertGreaterThanOrEqual(3, count($article->body));
        $this->assertLessThanOrEqual(4, count($article->body));
        foreach ($article->body as $paragraph) {
            $this->assertStringNotContainsString("\n", $paragraph, 'Each paragraph must be a single line.');
            $this->assertNotEmpty(trim($paragraph));
        }
    }

    public function test_good_run_generates_no_article(): void
    {
        [$game] = $this->buildScenario(results: ['W', 'W', 'D', 'W', 'W']);

        $articles = $this->service()->pressureArticles($game->refresh(), $this->nextMatch($game));

        $this->assertEmpty($articles, 'A winning team must not appear in the rumour mill.');
    }

    public function test_unknown_coach_name_is_never_invented(): void
    {
        [$game, $team] = $this->buildScenario(results: ['L', 'L', 'L', 'L'], coachName: null);

        $articles = $this->service()->pressureArticles($game->refresh(), $this->nextMatch($game));

        $article = $this->findArticleFor($articles, $team->id);
        $this->assertNotNull($article);
        // Generic headline naming the club, not a person.
        $this->assertSame("El entrenador del {$team->name} podría ser cesado", $article->headline);
        foreach ($article->body as $paragraph) {
            $this->assertStringNotContainsString('Test Coach', $paragraph);
        }
    }

    public function test_three_straight_losses_trigger_pressure(): void
    {
        [$game, $team] = $this->buildScenario(results: ['W', 'W', 'L', 'L', 'L']);

        $articles = $this->service()->pressureArticles($game->refresh(), $this->nextMatch($game));

        $this->assertNotNull($this->findArticleFor($articles, $team->id));
    }

    public function test_articles_are_stable_across_calls(): void
    {
        [$game] = $this->buildScenario(results: ['L', 'L', 'L', 'L', 'L']);

        $first = $this->service()->pressureArticles($game->refresh(), $this->nextMatch($game));
        $second = $this->service()->pressureArticles($game->refresh(), $this->nextMatch($game));

        $this->assertEquals(
            array_map(fn ($a) => [$a->headline, $a->body], $first),
            array_map(fn ($a) => [$a->headline, $a->body], $second),
            'The rumour mill must be deterministic per round.',
        );
    }

    /**
     * @param list<string> $results oldest-first W/D/L for the player's team
     * @return array{Game, Team}
     */
    private function buildScenario(array $results, ?string $coachName = 'Test Coach'): array
    {
        $esp1 = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create([
            'name' => 'Test WFC',
            'country' => 'ES',
            'manager_name' => $coachName,
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
        ]);

        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'team_id' => $team->id,
            'position' => 14,
            'played' => count($results),
            'points' => 3,
        ]);

        $date = Carbon::parse('2026-09-01');
        foreach ($results as $i => $result) {
            $opponent = Team::factory()->create(['country' => 'ES']);
            $home = $i % 2 === 0;
            GameMatch::factory()->forGame($game)->create([
                'competition_id' => 'ESP1',
                'home_team_id' => $home ? $team->id : $opponent->id,
                'away_team_id' => $home ? $opponent->id : $team->id,
                'home_score' => $home ? ($result === 'W' ? 2 : ($result === 'D' ? 1 : 0)) : ($result === 'W' ? 0 : ($result === 'D' ? 1 : 2)),
                'away_score' => $home ? ($result === 'W' ? 0 : ($result === 'D' ? 1 : 2)) : ($result === 'W' ? 2 : ($result === 'D' ? 1 : 0)),
                'played' => true,
                'scheduled_date' => $date->copy()->addDays($i * 7),
                'round_number' => $i + 1,
            ]);
        }

        return [$game, $team];
    }

    private function nextMatch(Game $game): GameMatch
    {
        return GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $game->team_id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'played' => false,
            'round_number' => 6,
            'scheduled_date' => Carbon::parse('2026-10-10'),
        ]);
    }

    /**
     * @param array<MatchNarrative> $articles
     */
    private function findArticleFor(array $articles, string $teamId): ?MatchNarrative
    {
        foreach ($articles as $article) {
            if (str_contains($article->headline, Team::find($teamId)->name)) {
                return $article;
            }
        }

        return null;
    }

    private function service(): ManagerPressureService
    {
        return app(ManagerPressureService::class);
    }
}

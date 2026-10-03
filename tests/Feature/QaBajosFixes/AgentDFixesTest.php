<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 (bugs bajos) — Agente D: B10, B11, B12, B13
 * (redes de la selección + crónica de prensa).
 */
class AgentDFixesTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function nationalGame(string $teamName, string $locale): Game
    {
        app()->setLocale($locale);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => $teamName, 'type' => 'national']);

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ])->fresh();
    }

    private function pressScenario(): array
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

    private function playedMatch(Game $game, Team $home, Team $away, string $date, int $homeScore, int $awayScore): GameMatch
    {
        return GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => true,
            'scheduled_date' => Carbon::parse($date),
            'round_number' => 5,
        ]);
    }

    private function chronicleHeadline(array $articles): ?string
    {
        foreach ($articles as $article) {
            if ($article->category === 'chronicle') {
                return $article->headline;
            }
        }

        return null;
    }

    private function chronicleBody0(array $articles): ?string
    {
        foreach ($articles as $article) {
            if ($article->category === 'chronicle') {
                return $article->body[0];
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // B10: fecha del anuncio de sede sin "de" en inglés
    // ------------------------------------------------------------------

    public function test_b10_venue_announcement_english_date_has_no_spanish_de(): void
    {
        $game = $this->nationalGame('England', 'en');
        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'played' => false,
            'scheduled_date' => '2026-10-10',
            'stadium_name' => 'Wembley',
            'neutral_venue_capacity' => 90000,
        ]);

        $post = app(NationalSocialService::class)->announceVenueConfirmed($game, $match);

        $this->assertNotNull($post);
        $this->assertStringContainsString('OFFICIAL!', $post->text);
        $this->assertStringNotContainsString(' de ', $post->text);
        $this->assertStringContainsString('Saturday 10 October', $post->text);
    }

    public function test_b10_venue_announcement_spanish_date_keeps_de(): void
    {
        $game = $this->nationalGame('Spain', 'es');
        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'played' => false,
            'scheduled_date' => '2026-10-10',
            'stadium_name' => 'Mestalla',
            'neutral_venue_capacity' => 45000,
        ]);

        $post = app(NationalSocialService::class)->announceVenueConfirmed($game, $match);

        $this->assertNotNull($post);
        $this->assertStringContainsString('¡OFICIAL!', $post->text);
        $this->assertStringContainsString('10 de octubre', $post->text);
    }

    // ------------------------------------------------------------------
    // B11: seguidores de España = "850 mil" en locale es
    // ------------------------------------------------------------------

    public function test_b11_followers_spain_shows_850k_in_spanish_locale(): void
    {
        // Con locale es, Team::name devuelve "España"; el servicio debe usar
        // el nombre en bruto ("Spain") para que coincida.
        $game = $this->nationalGame('Spain', 'es');
        $this->assertSame('España', $game->team->name);

        $this->assertSame('850 mil', app(NationalSocialService::class)->followers($game));
    }

    // ------------------------------------------------------------------
    // B12: la crónica empieza en mayúscula (es y en)
    // ------------------------------------------------------------------

    public function test_b12_chronicle_first_sentence_starts_uppercase_spanish(): void
    {
        [$game, $team, $opponent] = $this->pressScenario();
        $this->playedMatch($game, $team, $opponent, '2026-09-20', 2, 1);

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $first = $this->chronicleBody0($articles);

        $this->assertNotNull($first);
        $char = mb_substr($first, 0, 1);
        $this->assertSame(mb_strtoupper($char), $char, "BUG B12: la crónica empieza en minúscula: \"{$first}\"");
        $this->assertStringStartsWith('Victoria', $first);
    }

    public function test_b12_chronicle_first_sentence_starts_uppercase_english(): void
    {
        [$game, $team, $opponent] = $this->pressScenario();
        app()->setLocale('en');
        $this->playedMatch($game, $team, $opponent, '2026-09-20', 2, 1);

        $articles = app(PressNewsService::class)->articles($game->refresh(), null);
        $first = $this->chronicleBody0($articles);

        $this->assertNotNull($first);
        $char = mb_substr($first, 0, 1);
        $this->assertSame(mb_strtoupper($char), $char, "BUG B12: the report starts lowercase: \"{$first}\"");
    }

    // ------------------------------------------------------------------
    // B13: crónica estable con dos partidos en la misma fecha
    // ------------------------------------------------------------------

    public function test_b13_chronicle_same_date_matches_is_stable_and_deterministic(): void
    {
        // Club + filial el mismo finde: dos partidos jugados la misma fecha.
        [$game, $team, $opponent] = $this->pressScenario();
        $other = Team::factory()->create(['name' => 'Other WFC', 'country' => 'ES']);
        $this->playedMatch($game, $team, $opponent, '2026-09-20', 2, 0);
        $this->playedMatch($game, $team, $other, '2026-09-20', 0, 3);

        // El ganador determinista: el de mayor id (desempate por id).
        $teamIds = $game->userTeamIds();
        $expected = GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($expected);
        $expectedUserIsHome = in_array($expected->home_team_id, $teamIds, true);
        $expectedUserTeam = Team::find($expectedUserIsHome ? $expected->home_team_id : $expected->away_team_id);
        $expectedOpp = $expectedUserIsHome ? $expected->awayTeam : $expected->homeTeam;
        $expectedHeadline = 'Crónica: ' . $expectedUserTeam->name . ' '
            . ($expectedUserIsHome ? $expected->home_score : $expected->away_score)
            . '-'
            . ($expectedUserIsHome ? $expected->away_score : $expected->home_score)
            . ' ' . $expectedOpp->name;

        $headlines = [];
        for ($i = 0; $i < 10; $i++) {
            $headlines[] = $this->chronicleHeadline(app(PressNewsService::class)->articles($game->refresh(), null));
        }

        // Estable entre cargas (no parpadea) y sigue la regla de desempate.
        $this->assertCount(1, array_unique($headlines), 'La crónica no debe parpadear entre cargas con partidos en la misma fecha.');
        $this->assertSame($expectedHeadline, $headlines[0], 'El desempate por id debe elegir siempre el mismo partido.');
    }
}

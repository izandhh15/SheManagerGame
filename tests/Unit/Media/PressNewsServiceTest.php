<?php

namespace Tests\Unit\Media;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\PressNewsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Press newsroom: sale/signing rumours, previas, crónicas and injury news.
 *
 * Injury news is only published when the club has issued the official
 * medical statement; without it there is no injury article (at most a
 * soft rumour).
 */
class PressNewsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_chronicle_article_reports_last_match_with_scorers(): void
    {
        [$game, $team, $opponent] = $this->buildScenario();
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Test Scorer',
        ]);

        $match = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'home_score' => 2,
            'away_score' => 1,
            'played' => true,
            'scheduled_date' => Carbon::parse('2026-09-20'),
            'round_number' => 5,
        ]);

        MatchEvent::create([
            'game_match_id' => $match->id,
            'game_id' => $game->id,
            'team_id' => $team->id,
            'game_player_id' => $player->id,
            'event_type' => MatchEvent::TYPE_GOAL,
            'minute' => 23,
        ]);

        $articles = $this->service()->articles($game->refresh(), null);

        $chronicle = $this->findByCategory($articles, 'chronicle');
        $this->assertNotNull($chronicle, 'A played match must produce a crónica.');
        $this->assertStringContainsString('2-1', $chronicle->headline);
        $this->assertStringContainsString('Test Scorer', implode(' ', $chronicle->body));
        $this->assertNotEmpty($chronicle->source);
        foreach ($chronicle->body as $paragraph) {
            $this->assertStringNotContainsString("\n", $paragraph);
        }
    }

    public function test_preview_article_only_for_notable_fixtures(): void
    {
        [$game, $team, $opponent] = $this->buildScenario();

        // Ordinary league game, mid-table: no previa article.
        $plain = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'played' => false,
            'round_number' => 6,
            'scheduled_date' => Carbon::parse('2026-10-10'),
        ]);

        $articles = $this->service()->articles($game->refresh(), $plain);
        $this->assertNull($this->findByCategory($articles, 'preview'));

        // Cup tie: previa article.
        $cupTie = \App\Models\CupTie::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
        ]);
        $cup = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'played' => false,
            'round_number' => 7,
            'cup_tie_id' => $cupTie->id,
            'scheduled_date' => Carbon::parse('2026-10-17'),
        ]);

        $articles = $this->service()->articles($game->refresh(), $cup);
        $preview = $this->findByCategory($articles, 'preview');
        $this->assertNotNull($preview, 'A cup tie must produce a previa article.');
        $this->assertStringContainsString($opponent->name, $preview->headline);
        $this->assertGreaterThanOrEqual(3, count($preview->body));
    }

    public function test_injury_article_requires_official_statement(): void
    {
        [$game, $team] = $this->buildScenario();
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Test Injured',
            'injury_until' => Carbon::parse('2026-11-01'),
            'injury_type' => 'Ankle sprain',
        ]);

        // No statement: no injury article (at most a rumour).
        $articles = $this->service()->articles($game->refresh(), null);
        $this->assertNull(
            $this->findByCategory($articles, 'injury'),
            'Without an official statement there must be no injury news.'
        );

        // Official medical statement: injury article appears.
        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $team->name,
            'author_handle' => '@testwfc',
            'text' => '🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: Test Injured estará unas 4 semanas de baja. ¡Mucho ánimo, te esperamos! 💪',
            'context' => 'club_official',
        ]);

        $articles = $this->service()->articles($game->refresh(), null);
        $injury = $this->findByCategory($articles, 'injury');
        $this->assertNotNull($injury, 'An official statement must unlock the injury article.');
        $this->assertStringContainsString('Test Injured', $injury->headline);
        $this->assertStringContainsString('esguince de tobillo', implode(' ', $injury->body));
    }

    public function test_sale_rumor_article_from_real_bid(): void
    {
        [$game, $team, $opponent] = $this->buildScenario();
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Test Star',
        ]);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $opponent->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'status' => TransferOffer::STATUS_PENDING,
            'transfer_fee' => 5_000_000_00,
            'game_date' => Carbon::parse('2026-10-01'),
            'expires_at' => Carbon::parse('2026-11-01'),
        ]);

        $articles = $this->service()->articles($game->refresh(), null);
        $rumor = $this->findByCategory($articles, 'sale_rumor');
        $this->assertNotNull($rumor, 'A real bid must produce a sale rumour article.');
        $this->assertStringContainsString('Test Star', $rumor->headline);
        $this->assertStringContainsString($opponent->name, $rumor->headline);
    }

    public function test_signing_rumor_article_from_user_bid(): void
    {
        [$game, $team, $opponent] = $this->buildScenario();
        $target = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $opponent->id,
            'name' => 'Test Target',
        ]);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $target->id,
            'offering_team_id' => $team->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'status' => TransferOffer::STATUS_PENDING,
            'transfer_fee' => 3_000_000_00,
            'game_date' => Carbon::parse('2026-10-01'),
            'expires_at' => Carbon::parse('2026-11-01'),
        ]);

        $articles = $this->service()->articles($game->refresh(), null);
        $rumor = $this->findByCategory($articles, 'signing_rumor');
        $this->assertNotNull($rumor, 'A user bid must produce a signing rumour article.');
        $this->assertStringContainsString('Test Target', $rumor->headline);
    }

    public function test_articles_are_stable_across_calls(): void
    {
        [$game, $team, $opponent] = $this->buildScenario();
        GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'home_score' => 1,
            'away_score' => 1,
            'played' => true,
            'scheduled_date' => Carbon::parse('2026-09-20'),
            'round_number' => 5,
        ]);

        $first = $this->service()->articles($game->refresh(), null);
        $second = $this->service()->articles($game->refresh(), null);

        $this->assertEquals(
            array_map(fn ($a) => [$a->category, $a->headline, $a->body], $first),
            array_map(fn ($a) => [$a->category, $a->headline, $a->body], $second),
            'Press articles must be deterministic.'
        );
    }

    public function test_tournament_mode_has_no_press_articles(): void
    {
        [$game] = $this->buildScenario();
        $game->update(['game_mode' => Game::MODE_TOURNAMENT]);

        $this->assertSame([], $this->service()->articles($game->refresh(), null));
    }

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
}

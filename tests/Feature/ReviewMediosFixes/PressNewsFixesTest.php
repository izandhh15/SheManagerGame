<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\PressNewsService;
use App\Modules\Player\Services\PlayerHistoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fixes 1, 2 y 4 de la revisión de medios (PressNewsService).
 *
 * 1. La previa como visitante decía "El X recibe a domicilio al Y"
 *    (contradicción literal): ahora el visitante "visita"/"juega a domicilio".
 * 2. saleRumorArticle imprimía "ha puesto € 0 sobre la mesa" para
 *    pre-contratos: ahora esos rumores salen sin cifra.
 * 4. homecomingLine() hacía 1 query a game_transfers por jugadora (N+1):
 *    ahora son 2 queries en total (plantillas + traspasos).
 */
class PressNewsFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_as_visitor_does_not_say_recibe_a_domicilio(): void
    {
        app()->setLocale('es');
        [$game, $userTeam, $opponent, $match] = $this->previewScenario(userIsHome: false);

        $article = $this->invokePreview($game, $match);

        $this->assertInstanceOf(MatchNarrative::class, $article);
        $lede = $article->body[0];
        $this->assertStringNotContainsString(
            'recibe a domicilio',
            $lede,
            'La previa como visitante no puede decir "recibe a domicilio".'
        );
        $this->assertTrue(
            str_contains($lede, 'visita al') || str_contains($lede, 'a domicilio'),
            "La previa como visitante debe usar 'visita' o 'a domicilio'. Lede: {$lede}"
        );
    }

    public function test_preview_as_home_keeps_recibe(): void
    {
        app()->setLocale('es');
        [$game, $userTeam, $opponent, $match] = $this->previewScenario(userIsHome: true);

        $article = $this->invokePreview($game, $match);

        $lede = $article->body[0];
        $this->assertStringContainsString('recibe', $lede);
        $this->assertStringNotContainsString('a domicilio', $lede);
    }

    public function test_preview_as_visitor_english(): void
    {
        app()->setLocale('en');
        [$game, $userTeam, $opponent, $match] = $this->previewScenario(userIsHome: false);

        $article = $this->invokePreview($game, $match);

        $lede = $article->body[0];
        $this->assertStringNotContainsString('recibe', $lede);
        $this->assertTrue(
            str_contains($lede, 'away from home') || str_contains($lede, 'travel to face'),
            "La previa en inglés como visitante debe decir 'away from home' o 'travel to face'. Lede: {$lede}"
        );
    }

    public function test_sale_rumor_pre_contract_has_no_zero_fee(): void
    {
        app()->setLocale('es');
        [$game, $userTeam] = $this->basicGame();

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'name' => 'Jugadora Cotizada',
        ]);
        $bidder = Team::factory()->create(['name' => 'Club Puja ES']);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $bidder->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => Carbon::parse('2026-12-31'),
            'game_date' => $game->current_date,
        ]);

        $article = $this->invokePrivate('saleRumorArticle', [$game->refresh(), 1]);

        $this->assertInstanceOf(MatchNarrative::class, $article);
        $fullText = implode(' ', $article->body);
        $this->assertStringNotContainsString(
            '€ 0',
            $fullText,
            'El rumor de pre-contrato no puede mostrar "€ 0 sobre la mesa".'
        );
        $this->assertStringContainsString('precontrato', mb_strtolower($fullText));
    }

    public function test_sale_rumor_normal_bid_still_shows_fee(): void
    {
        app()->setLocale('es');
        [$game, $userTeam] = $this->basicGame();

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'name' => 'Jugadora Cotizada',
        ]);
        $bidder = Team::factory()->create(['name' => 'Club Puja ES']);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $bidder->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => Carbon::parse('2026-12-31'),
            'game_date' => $game->current_date,
        ]);

        $article = $this->invokePrivate('saleRumorArticle', [$game->refresh(), 1]);

        $this->assertInstanceOf(MatchNarrative::class, $article);
        $this->assertStringContainsString('sobre la mesa', $article->body[0]);
    }

    public function test_homecoming_line_runs_in_two_queries_and_matches_history_service(): void
    {
        app()->setLocale('es');
        [$game, $userTeam, $opponent] = $this->basicGame();

        // Una jugadora del equipo del usuario que viene del rival.
        $returner = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'name' => 'Zara Retornada',
            'overall_score' => 85,
        ]);
        GameTransfer::create([
            'game_id' => $game->id,
            'game_player_id' => $returner->id,
            'from_team_id' => $opponent->id,
            'to_team_id' => $userTeam->id,
            'type' => GameTransfer::TYPE_TRANSFER,
            'window' => 'summer',
            'season' => '2025',
        ]);
        // Relleno: jugadoras sin historial (no deben disparar queries extra).
        GamePlayer::factory()->count(10)->create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'overall_score' => 60,
        ]);
        GamePlayer::factory()->count(10)->create([
            'game_id' => $game->id,
            'team_id' => $opponent->id,
            'overall_score' => 60,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $opponent->id,
            'away_team_id' => $userTeam->id,
        ]);

        // Equivalencia semántica con el servicio original.
        $this->assertTrue(
            app(PlayerHistoryService::class)->returnsHomeAgainst($game, $returner, $opponent->id),
            'El servicio de historial debe detectar el retorno.'
        );

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $line = $this->invokePrivate('homecomingLine', [$game->refresh(), $match, true]);

        $this->assertNotNull($line, 'Debe detectarse el "vuelve a casa".');
        $this->assertStringContainsString('Zara Retornada', $line);
        $this->assertLessThanOrEqual(
            4,
            $queryCount,
            "homecomingLine() debe resolverse en un puñado de queries, no una por jugadora (fueron {$queryCount})."
        );
    }

    public function test_homecoming_line_returns_null_without_returners(): void
    {
        app()->setLocale('es');
        [$game, $userTeam, $opponent] = $this->basicGame();
        GamePlayer::factory()->count(5)->create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $opponent->id,
            'away_team_id' => $userTeam->id,
        ]);

        $line = $this->invokePrivate('homecomingLine', [$game->refresh(), $match, true]);

        $this->assertNull($line);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{Game, Team, Team} */
    private function basicGame(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $userTeam = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $opponent = Team::factory()->create(['name' => 'Rival WFC', 'country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $userTeam, $opponent];
    }

    /** @return array{Game, Team, Team, GameMatch} */
    private function previewScenario(bool $userIsHome): array
    {
        [$game, $userTeam, $opponent] = $this->basicGame();

        $tie = CupTie::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $userIsHome ? $userTeam->id : $opponent->id,
            'away_team_id' => $userIsHome ? $opponent->id : $userTeam->id,
            'cup_tie_id' => $tie->id,
        ]);

        return [$game->refresh(), $userTeam, $opponent, $match];
    }

    private function invokePreview(Game $game, GameMatch $match): ?MatchNarrative
    {
        return $this->invokePrivate('previewArticle', [$game, $match, 1]);
    }

    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(PressNewsService::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke(app(PressNewsService::class), ...$args);
    }
}

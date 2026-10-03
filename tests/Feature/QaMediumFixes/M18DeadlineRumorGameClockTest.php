<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M18 (QA agent-15 / FEED-05): los rumores del deadline day se
 * regeneraban para las mismas ofertas si pasaban 3+ días reales.
 *
 * Bug original: la deduplicación usaba
 * where('created_at', '>=', now()->subDays(3)) — reloj REAL — mientras la
 * semana límite (25-31 ene) es tiempo de JUEGO. Quien jugaba una jornada
 * por día real veía 2-3 oleadas de rumores con textos idénticos sobre las
 * mismas ofertas.
 *
 * Fix: la migración 2026_10_03_000039 añade `game_date` a social_posts y
 * generateDeadlineDayRumors() deduplica por (game_id, context,
 * game_date): como mucho una oleada por fecha de juego.
 *
 * Este test es el port (invertido) del repro del QA
 * (InternetFeedDeadlineRumorClockTest): mover el reloj real 4 días con la
 * misma fecha de juego NO regenera la oleada; avanzar la fecha del juego
 * sí genera una nueva.
 */
class M18DeadlineRumorGameClockTest extends TestCase
{
    use RefreshDatabase;

    private function deadlineFixture(): \App\Models\Game
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = \App\Models\Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'current_date' => '2026-01-28', // deadline week, winter window open
            'board_confidence' => 70,
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Rumoreada Test',
            'overall_score' => 85,
        ]);
        $buyer = Team::factory()->create(['name' => 'Comprador FC']);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyer->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => '2026-02-05',
            'game_date' => '2026-01-28',
        ]);

        return $game;
    }

    private function rumorCount(\App\Models\Game $game): int
    {
        return SocialPost::where('game_id', $game->id)
            ->where('context', 'deadline_rumor')
            ->count();
    }

    public function test_rumors_not_regenerated_when_only_real_time_passes(): void
    {
        $game = $this->deadlineFixture();
        $svc = app(SocialMediaService::class);

        mt_srand(11);

        $svc->generateDeadlineDayRumors($game);
        $first = $this->rumorCount($game);
        $this->assertGreaterThan(0, $first, 'no rumor batch generated at all');

        // Pasan 4 días REALES; la fecha del juego sigue en 28-ene (misma
        // semana límite, mismas ofertas activas).
        Carbon::setTestNow(Carbon::now()->addDays(4));
        try {
            $svc->generateDeadlineDayRumors($game);
        } finally {
            Carbon::setTestNow();
        }

        $total = $this->rumorCount($game);

        $this->assertSame(
            $first,
            $total,
            "M18: rumor batch generated twice ({$first} -> {$total} posts) for the same "
                . 'offers within the same game deadline week, just because 3+ real days passed.'
        );
    }

    /**
     * Sanidad: la deduplicación es por fecha de juego, no "una vez y nunca
     * más" — avanzar la fecha del juego sí permite una oleada nueva.
     */
    public function test_new_game_date_allows_a_new_rumor_batch(): void
    {
        $game = $this->deadlineFixture();
        $svc = app(SocialMediaService::class);

        mt_srand(11);

        $svc->generateDeadlineDayRumors($game);
        $first = $this->rumorCount($game);
        $this->assertGreaterThan(0, $first, 'no rumor batch generated at all');

        // Avanza la FECHA DEL JUEGO al día siguiente (aún en deadline week).
        $game->current_date = '2026-01-29';
        $game->save();

        $svc->generateDeadlineDayRumors($game);
        $total = $this->rumorCount($game);

        $this->assertSame(
            $first * 2,
            $total,
            'M18: advancing the game date should allow a fresh rumor batch for the new game day.'
        );

        // Y repetir el mismo día de juego sigue sin duplicar.
        $svc->generateDeadlineDayRumors($game);
        $this->assertSame(
            $total,
            $this->rumorCount($game),
            'M18: same game date must not regenerate the batch.'
        );
    }
}

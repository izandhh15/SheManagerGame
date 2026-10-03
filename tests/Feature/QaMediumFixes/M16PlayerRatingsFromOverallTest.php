<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M16 (QA agent-15 / FEED-03): las notas de "destacada"/"peor" de
 * la rueda de prensa eran ruido puro.
 *
 * Bug original: SocialMediaService::playerRatings() calculaba
 * $base = ($p->overall ?? 70) / 10, pero GamePlayer NO tiene atributo
 * `overall` (la columna es `overall_score` y no hay accessor) → $base
 * siempre 7.0 y la nota 7.0 + rand(-1.5, 1.5). Una suplente de media 55
 * podía salir como estrella y la crack de 95 como la peor, y las
 * reacciones de los fans se disparaban al azar respecto a la calidad real.
 *
 * Fix: usar $p->overall_score para que las notas deriven de la calidad
 * real de la plantilla.
 *
 * Este test es el port (invertido) del repro del QA
 * (InternetFeedStarRatingNoiseTest): plantilla con una jugadora de
 * overall_score 95 y otra de 50; 300 lecturas de playerRatings() vía
 * reflexión. Con el fix, la media de la de 95 ronda 9.5 y la de 50 ronda
 * 5.0.
 */
class M16PlayerRatingsFromOverallTest extends TestCase
{
    use RefreshDatabase;

    public function test_ratings_derive_from_overall_score(): void
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = \App\Models\Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'current_date' => '2026-08-20',
        ]);

        $star = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Crack Noventa y Cinco',
            'overall_score' => 95,
        ]);
        $flop = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Suplente Cincuenta',
            'overall_score' => 50,
        ]);
        GamePlayer::factory()->count(12)->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'overall_score' => 70,
        ]);

        $match = \App\Models\GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id,
            'home_score' => 2,
            'away_score' => 1,
            'played' => true,
        ]);

        $svc = app(SocialMediaService::class);
        $method = new \ReflectionMethod(SocialMediaService::class, 'playerRatings');
        $method->setAccessible(true);

        mt_srand(99);

        $starSum = 0.0;
        $flopSum = 0.0;
        $reads = 300;

        for ($i = 0; $i < $reads; $i++) {
            foreach ($method->invoke($svc, $game, $match) as $r) {
                if ($r['id'] === $star->id) {
                    $starSum += $r['rating'];
                } elseif ($r['id'] === $flop->id) {
                    $flopSum += $r['rating'];
                }
            }
        }

        $starMean = $starSum / $reads;
        $flopMean = $flopSum / $reads;

        $this->assertGreaterThan(
            9.0,
            $starMean,
            "M16: 95-rated player's mean press rating is {$starMean} (expected ~9.5 if derived from overall_score)."
        );
        $this->assertLessThan(
            6.0,
            $flopMean,
            "M16: 50-rated player's mean press rating is {$flopMean} (expected ~5.0 if derived from overall_score)."
        );
        $this->assertGreaterThan(
            3.0,
            $starMean - $flopMean,
            'M16: star and flop should be ~4.5 rating points apart, not noise around 7.0.'
        );
    }

    /**
     * La opción "Elogiar a la destacada" apunta a la jugadora de 95, con
     * nota alta y estable (no al azar de la plantilla).
     */
    public function test_press_options_pick_real_star(): void
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = \App\Models\Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'current_date' => '2026-08-20',
        ]);

        $star = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Crack Noventa y Cinco',
            'overall_score' => 95,
        ]);
        GamePlayer::factory()->count(13)->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'overall_score' => 65,
        ]);

        $match = \App\Models\GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id,
            'home_score' => 2,
            'away_score' => 1,
            'played' => true,
        ]);

        mt_srand(1234);

        $options = app(SocialMediaService::class)->pressOptions($game, $match);
        $praiseStar = collect($options)->firstWhere('key', 'praise_star');

        $this->assertNotNull($praiseStar, 'missing praise_star press option');
        $this->assertSame(
            $star->id,
            $praiseStar['player']['id'],
            'M16: "Elogiar a la destacada" should target the 95-rated player.'
        );
        $this->assertGreaterThanOrEqual(
            8.5,
            (float) $praiseStar['player']['rating'],
            'M16: the star\'s press rating should be high, not noise around 7.0.'
        );
    }
}

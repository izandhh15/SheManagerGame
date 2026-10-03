<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M14 (QA agent-15 / FEED-01): la misma frase aparecía dos veces
 * en una oleada de fans (50/60 oleadas).
 *
 * Bug original: SocialMediaService::generatePost() elegía
 * $templates[array_rand($templates)] sin deduplicar texto ($usedFans solo
 * dedup nombre|handle). Con 6-10 posts de ~14 plantillas positivas, la
 * colisión era casi segura.
 *
 * Fix: generatePost() lleva $usedTexts (elección sin reemplazo dentro de
 * la oleada); si la oleada agotase el pool, recurre a la lista completa.
 *
 * Este test es el port (invertido) del repro del QA
 * (InternetFeedDuplicateTextTest): con el fix, 0/30 oleadas contienen el
 * mismo texto dos veces.
 */
class M14FeedWaveUniqueTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_press_wave_never_repeats_the_same_text(): void
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $rival = Team::factory()->create();

        $game = Game::factory()->create([
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

        $svc = app(SocialMediaService::class);

        mt_srand(20261003); // misma secuencia determinista que el repro QA

        $batches = 30;
        $withDup = 0;
        $dupExample = null;

        for ($i = 0; $i < $batches; $i++) {
            $match = GameMatch::factory()->create([
                'game_id' => $game->id,
                'home_team_id' => $team->id,
                'away_team_id' => Team::factory()->create()->id,
                'home_score' => 3,
                'away_score' => 0,
                'played' => true,
            ]);

            $svc->makeStatement($game, $match, 'praise_team'); // win -> positive mood

            $texts = SocialPost::where('game_id', $game->id)
                ->where('context', 'post_match')
                ->where('match_id', $match->id)
                ->pluck('text')
                ->all();

            $this->assertNotEmpty($texts, 'press wave generated no posts');

            if (count($texts) !== count(array_unique($texts))) {
                $withDup++;
                if ($dupExample === null) {
                    $counts = array_count_values($texts);
                    arsort($counts);
                    $dupExample = array_key_first($counts);
                }
            }
        }

        $this->assertSame(
            0,
            $withDup,
            "M14: {$withDup}/{$batches} press waves contained the same text twice. "
                . 'Example duplicated text: "' . $dupExample . '"'
        );
    }
}

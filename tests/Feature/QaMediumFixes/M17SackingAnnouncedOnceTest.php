<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M17 (QA agent-15 / FEED-04): el despido se anunciaba una vez por
 * rueda de prensa (textos idénticos repetidos).
 *
 * Bug original: makeStatement() llamaba a generateSackingPosts() en CADA
 * declaración hecha con board_confidence < 15, y nada impedía declarar
 * tras el despido. Los 3 textos son cadenas fijas → el feed acumulaba
 * "🚨🚨 OFICIAL: El club destituye al entrenador" repetido N veces.
 *
 * Fix: generateSackingPosts() no emite nada si ya existen posts de
 * contexto 'sacked' para esa partida: el despido se anuncia UNA vez.
 *
 * Este test es el port (invertido) del repro del QA
 * (InternetFeedSackingRepeatTest): dos ruedas de prensa con confianza < 15
 * → siguen siendo 3 anuncios (no 6).
 */
class M17SackingAnnouncedOnceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sacking_announced_only_once(): void
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = \App\Models\Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'current_date' => '2026-08-20',
            'board_confidence' => 10,
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
            'home_score' => 0,
            'away_score' => 2,
            'played' => true,
        ]);

        $svc = app(SocialMediaService::class);

        mt_srand(5);

        // blame_referee tras derrota: impacto -4 → confianza 10 → 6 (< 15): despido.
        $svc->makeStatement($game, $match, 'blame_referee');

        $first = SocialPost::where('game_id', $game->id)
            ->where('context', 'sacked')
            ->count();
        $this->assertSame(3, $first, 'first sacking did not generate the 3 announcement posts');

        // Segunda rueda de prensa (otro partido), ya con el despido anunciado.
        $match2 = \App\Models\GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'away_team_id' => Team::factory()->create()->id,
            'home_score' => 0,
            'away_score' => 1,
            'played' => true,
        ]);
        $svc->makeStatement($game, $match2, 'blame_referee');

        $total = SocialPost::where('game_id', $game->id)
            ->where('context', 'sacked')
            ->count();

        $distinct = SocialPost::where('game_id', $game->id)
            ->where('context', 'sacked')
            ->distinct()
            ->pluck('text')
            ->all();

        $this->assertSame(
            3,
            $total,
            "M17: {$total} sacking announcements in the feed after two press conferences "
                . '(' . count($distinct) . ' distinct texts — the same sacking posted twice).'
        );
    }
}

<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Tests\Traits\CreatesLineups;

/**
 * Shared fixture for the R4/R10/R13/R14/R19 regression tests (fase 5,
 * revisión línea a línea): a game with the user's team, an opponent, a
 * league match with full lineups + benches.
 *
 * @return array{0: Game, 1: Team, 2: Team, 3: GameMatch, 4: \Illuminate\Support\Collection, 5: \Illuminate\Support\Collection, 6: \Illuminate\Support\Collection, 7: \Illuminate\Support\Collection}
 */
trait BuildsReviewScenario
{
    use CreatesLineups;

    private function buildReviewScenario(): array
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

        $homeLineup = $this->createLineup($game, $team);
        $awayLineup = $this->createLineup($game, $opponent);
        $homeBench = $this->createBenchPlayers($game, $team, 7);
        $awayBench = $this->createBenchPlayers($game, $opponent, 7);

        $match = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'home_lineup' => $homeLineup->pluck('id')->all(),
            'away_lineup' => $awayLineup->pluck('id')->all(),
            'played' => false,
            'scheduled_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $team, $opponent, $match, $homeLineup, $awayLineup, $homeBench, $awayBench];
    }

    /**
     * Emulate PHP 32-bit crc32() semantics on this 64-bit box (pattern
     * ported from the C6 QA regression tests).
     */
    private function signed32(int $unsigned): int
    {
        return $unsigned >= 2 ** 31 ? $unsigned - 2 ** 32 : $unsigned;
    }
}

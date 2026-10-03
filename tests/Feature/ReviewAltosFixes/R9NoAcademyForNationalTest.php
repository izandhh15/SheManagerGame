<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Events\SeasonStarted;
use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\Team;
use App\Models\User;
use App\Modules\Academy\Listeners\GenerateInitialAcademyBatch;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\YouthAcademyClosingProcessor;
use App\Modules\Season\Processors\YouthAcademyPromotionProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R9 [ALTA]: GenerateInitialAcademyBatch did not filter by team type, so
 * SeasonStarted also generated phantom academy players for national-team
 * games — promoteOveragePlayers() would later promote those invented
 * players into GamePlayer of the national side (invented players are
 * forbidden). The listener now returns early unless the team is a club.
 */
class R9NoAcademyForNationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_game_generates_no_academy_batch(): void
    {
        $user = User::factory()->create();
        $national = Team::factory()->create([
            'type' => 'national',
            'is_placeholder' => false,
            'fifa_code' => 'ESP',
        ]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $national->id,
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);

        app(GenerateInitialAcademyBatch::class)->handle(new SeasonStarted($game));

        $this->assertSame(
            0,
            AcademyPlayer::where('game_id', $game->id)->count(),
            'A national-team game must never receive synthetic academy players'
        );
    }

    public function test_club_game_still_generates_academy_batch(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => 2026,
            'youth_academy_tier' => 3,
        ]);

        app(GenerateInitialAcademyBatch::class)->handle(new SeasonStarted($game));

        $this->assertGreaterThan(
            0,
            AcademyPlayer::where('game_id', $game->id)->count(),
            'A club game must still receive its academy batch'
        );
    }

    public function test_closing_processor_skips_national_games(): void
    {
        $game = $this->nationalGame();
        $data = new SeasonTransitionData(oldSeason: '2026', newSeason: '2027', competitionId: 'ESP1');

        $result = app(YouthAcademyClosingProcessor::class)->process($game, $data);

        $this->assertSame($data, $result);
        $this->assertSame(0, AcademyPlayer::where('game_id', $game->id)->count());
    }

    public function test_promotion_processor_skips_national_games(): void
    {
        $game = $this->nationalGame();
        $data = new SeasonTransitionData(oldSeason: '2026', newSeason: '2027', competitionId: 'ESP1');

        $result = app(YouthAcademyPromotionProcessor::class)->process($game, $data);

        $this->assertSame($data, $result);
        $this->assertSame(0, AcademyPlayer::where('game_id', $game->id)->count());
    }

    private function nationalGame(): Game
    {
        $user = User::factory()->create();
        $national = Team::factory()->create([
            'type' => 'national',
            'is_placeholder' => false,
            'fifa_code' => 'ESP',
        ]);

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $national->id,
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);
    }
}
